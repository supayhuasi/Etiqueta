<?php
/**
 * API unificada de escaneo
 * detecta el tipo de código y delega al módulo correspondiente:
 *  - asistencias (código EMP...)
 *  - producción (tabla ecommerce_produccion_items_barcode)
 *  - entrega de productos (tabla productos)
 *  - detalles adicionales (placeholder)
 * También maneja acciones de producción (iniciar, terminar, rechazar) cuando se envía
 * el campo `accion`.
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

session_start();
$usuario_id = $_SESSION['user_id']
    ?? ($_SESSION['usuario_id']
    ?? ($_SESSION['user']['id'] ?? null));
$rol = strtolower(trim((string)($_SESSION['rol'] ?? ($_SESSION['user']['rol'] ?? ''))));

if (!$usuario_id) {
    http_response_code(403);
    echo json_encode([ 'success' => false, 'message' => 'Usuario no autenticado' ]);
    exit;
}

function resolve_http_status_from_exception(Throwable $e): int {
    $code = $e->getCode();

    if (is_int($code)) {
        $status = $code;
    } elseif (is_string($code) && ctype_digit($code)) {
        $status = (int)$code;
    } else {
        $status = 400;
    }

    if ($status < 100 || $status > 599) {
        $status = 400;
    }

    return $status;
}

function table_exists(PDO $pdo, string $table): bool {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
        return false;
    }
    $quoted = $pdo->quote($table);
    $stmt = $pdo->query("SHOW TABLES LIKE {$quoted}");
    return $stmt ? (bool)$stmt->fetchColumn() : false;
}

function column_exists(PDO $pdo, string $table, string $column): bool {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
        return false;
    }
    $quotedColumn = $pdo->quote($column);
    $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE {$quotedColumn}");
    return $stmt ? (bool)$stmt->fetchColumn() : false;
}

function first_existing_column(PDO $pdo, string $table, array $candidates): ?string {
    foreach ($candidates as $candidate) {
        if (column_exists($pdo, $table, $candidate)) {
            return $candidate;
        }
    }
    return null;
}

function qi(string $identifier): string {
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function get_table_columns_map(PDO $pdo, string $table): array {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
        return [];
    }
    $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $map = [];
    foreach ($rows as $row) {
        if (!empty($row['Field'])) {
            $map[$row['Field']] = $row;
        }
    }
    return $map;
}

function parse_enum_values(string $type): array {
    if (!preg_match('/^enum\((.*)\)$/i', trim($type), $m)) {
        return [];
    }

    $inside = $m[1];
    preg_match_all("/'((?:\\\\'|[^'])*)'/", $inside, $matches);
    $values = [];
    foreach (($matches[1] ?? []) as $raw) {
        $values[] = str_replace("\\'", "'", $raw);
    }
    return $values;
}

function resolve_produccion_estado(PDO $pdo, string $etapa): string {
    static $cache = null;

    if (!is_array($cache)) {
        $cache = [
            'corte' => 'en_corte',
            'armado' => 'armado',
            'terminado' => 'terminado'
        ];

        if (table_exists($pdo, 'ecommerce_produccion_items_barcode') && column_exists($pdo, 'ecommerce_produccion_items_barcode', 'estado')) {
            $cols = get_table_columns_map($pdo, 'ecommerce_produccion_items_barcode');
            $type = strtolower((string)($cols['estado']['Type'] ?? ''));
            $enumValues = parse_enum_values($type);

            if (!empty($enumValues)) {
                $pick = function(array $candidates) use ($enumValues): string {
                    foreach ($candidates as $candidate) {
                        if (in_array($candidate, $enumValues, true)) {
                            return $candidate;
                        }
                    }
                    return $enumValues[0];
                };

                $cache['corte'] = $pick(['en_corte', 'corte', 'pendiente', 'en_produccion', 'produccion']);
                $cache['armado'] = $pick(['armado', 'en_armado', 'en_produccion', 'produccion']);
                $cache['terminado'] = $pick(['terminado', 'finalizado', 'completado', 'entregado']);
            }
        }
    }

    return $cache[$etapa] ?? 'terminado';
}

function ensure_produccion_scans_schema(PDO $pdo): void {
    static $initialized = false;
    if ($initialized) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS ecommerce_produccion_scans (
        id INT PRIMARY KEY AUTO_INCREMENT,
        produccion_item_id INT NOT NULL,
        orden_produccion_id INT NOT NULL,
        pedido_id INT NOT NULL,
        usuario_id INT NOT NULL,
        etapa ENUM('corte','armado','terminado') NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_item (produccion_item_id),
        INDEX idx_usuario (usuario_id),
        INDEX idx_orden (orden_produccion_id),
        INDEX idx_fecha (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $initialized = true;
}

try {
    $estado_corte = resolve_produccion_estado($pdo, 'corte');
    $estado_armado = resolve_produccion_estado($pdo, 'armado');
    $estado_terminado = resolve_produccion_estado($pdo, 'terminado');

    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    if (!is_array($data)) {
        $data = $_POST ?? [];
    }
    if (!is_array($data)) {
        $data = [];
    }

    // Si se indica una acción, la procesamos primero (mismo formato que la API de producción)
    if (isset($data['accion'])) {
        ensure_produccion_scans_schema($pdo);
        $accion = $data['accion'];
        // Copiado/adaptado de orden_produccion_escaneo_api.php
        if (in_array($accion, ['iniciar','terminar','rechazar'])) {
            $item_id = $data['item_id'] ?? 0;
            if ($item_id <= 0) {
                throw new Exception('ID de item inválido');
            }

            // cargar estado actual del item
            $stmt = $pdo->prepare("SELECT estado FROM ecommerce_produccion_items_barcode WHERE id = ?");
            $stmt->execute([$item_id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$item) {
                throw new Exception('Item no encontrado');
            }

            if ($accion === 'iniciar') {
                $estados_inicio_validos = array_unique([$estado_corte, 'en_corte', 'corte', 'pendiente']);
                if (!in_array((string)$item['estado'], $estados_inicio_validos, true)) {
                    throw new Exception('Este item ya fue iniciado');
                }
                $stmt = $pdo->prepare("UPDATE ecommerce_produccion_items_barcode
                    SET estado = ?, usuario_inicio = ?, fecha_inicio = NOW() WHERE id = ?");
                $stmt->execute([$estado_armado, $usuario_id, $item_id]);
                $stmt = $pdo->prepare("INSERT INTO ecommerce_produccion_scans
                    (produccion_item_id, orden_produccion_id, pedido_id, usuario_id, etapa)
                    SELECT pib.id, pib.orden_produccion_id, op.pedido_id, ?, 'armado'
                    FROM ecommerce_produccion_items_barcode pib
                    JOIN ecommerce_ordenes_produccion op ON op.id = pib.orden_produccion_id
                    WHERE pib.id = ?");
                $stmt->execute([$usuario_id, $item_id]);
                echo json_encode(['success'=>true,'message'=>'✅ Producción iniciada correctamente']);
                exit;
            }

            if ($accion === 'terminar') {
                $estados_terminar_validos = array_unique([$estado_armado, 'armado', 'en_armado', 'en_produccion']);
                if (!in_array((string)$item['estado'], $estados_terminar_validos, true)) {
                    throw new Exception('Este item no está en armado');
                }
                $stmt = $pdo->prepare("UPDATE ecommerce_produccion_items_barcode
                    SET estado = ?, usuario_termino = ?, fecha_termino = NOW() WHERE id = ?");
                $stmt->execute([$estado_terminado, $usuario_id, $item_id]);
                $stmt = $pdo->prepare("INSERT INTO ecommerce_produccion_scans
                    (produccion_item_id, orden_produccion_id, pedido_id, usuario_id, etapa)
                    SELECT pib.id, pib.orden_produccion_id, op.pedido_id, ?, 'terminado'
                    FROM ecommerce_produccion_items_barcode pib
                    JOIN ecommerce_ordenes_produccion op ON op.id = pib.orden_produccion_id
                    WHERE pib.id = ?");
                $stmt->execute([$usuario_id, $item_id]);

                // verificar si la orden completa se terminó
                $stmt = $pdo->prepare("SELECT COUNT(*) as total,
                       SUM(CASE WHEN estado = ? THEN 1 ELSE 0 END) as terminados
                    FROM ecommerce_produccion_items_barcode
                    WHERE orden_produccion_id = (
                        SELECT orden_produccion_id FROM ecommerce_produccion_items_barcode WHERE id = ?
                    )");
                $stmt->execute([$estado_terminado, $item_id]);
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);

                $mensaje = '✅ Item terminado correctamente';
                if ($stats['total'] == $stats['terminados']) {
                    $stmt = $pdo->prepare("UPDATE ecommerce_ordenes_produccion
                        SET estado = 'terminado'
                        WHERE id = (
                            SELECT orden_produccion_id FROM ecommerce_produccion_items_barcode WHERE id = ?
                        )");
                    $stmt->execute([$item_id]);
                    $mensaje = '🎉 Item terminado. ¡Orden de producción completada!';
                }

                echo json_encode(['success'=>true,'message'=>$mensaje]);
                exit;
            }

            if ($accion === 'rechazar') {
                $observaciones = $data['observaciones'] ?? 'Rechazado por operario';
                $stmt = $pdo->prepare("UPDATE ecommerce_produccion_items_barcode
                    SET estado = 'rechazado', observaciones = ?, usuario_termino = ?, fecha_termino = NOW()
                    WHERE id = ?");
                $stmt->execute([$observaciones, $usuario_id, $item_id]);
                echo json_encode(['success'=>true,'message'=>'❌ Item rechazado. Motivo registrado.']);
                exit;
            }
        }

        throw new Exception('Acción no válida');
    }

    if (!isset($data['codigo']) || trim($data['codigo']) === '') {
        throw new Exception('Código de barras no proporcionado');
    }

    $codigo = strtoupper(preg_replace('/\s+/', '', (string)$data['codigo']));
    $codigo_limpio = trim($codigo, "*\"' ");
    $codigo_solo_alnum_guion = preg_replace('/[^A-Z0-9\-]/', '', $codigo_limpio);

    // 1) Asistencias - formato EMP000001
    if (preg_match('/^EMP(\d{1,6})$/', $codigo, $m)) {
        if (!in_array($rol, ['ventas','operario','admin'])) {
            throw new Exception('Sin permiso para registrar asistencias');
        }

        if (!table_exists($pdo, 'empleados') || !table_exists($pdo, 'asistencias')) {
            throw new Exception('El módulo de asistencias no está instalado correctamente');
        }

        $empleado_id = (int)$m[1];
        $col_empleado_asist = first_existing_column($pdo, 'asistencias', ['empleado_id', 'id_empleado', 'empleado']);
        $col_pk_asist = first_existing_column($pdo, 'asistencias', ['id', 'asistencia_id', 'id_asistencia']);
        $col_fecha_asist = first_existing_column($pdo, 'asistencias', ['fecha', 'fecha_asistencia', 'dia']);
        $col_fecha_creacion_asist = first_existing_column($pdo, 'asistencias', ['fecha_creacion', 'created_at']);
        $col_hora_entrada_asist = first_existing_column($pdo, 'asistencias', ['hora_entrada', 'hora_ingreso', 'entrada']);
        $col_hora_salida_asist = first_existing_column($pdo, 'asistencias', ['hora_salida', 'hora_egreso', 'salida']);
        $col_estado_asist = first_existing_column($pdo, 'asistencias', ['estado']);
        $col_creado_por_asist = first_existing_column($pdo, 'asistencias', ['creado_por', 'usuario_id']);

        if (!$col_empleado_asist) {
            throw new Exception('No se encontró columna de empleado en asistencias');
        }

        $sql_empleado = "SELECT id, nombre FROM empleados WHERE id = ?";
        if (column_exists($pdo, 'empleados', 'activo')) {
            $sql_empleado .= " AND activo = 1";
        }

        $stmt = $pdo->prepare($sql_empleado);
        $stmt->execute([$empleado_id]);
        $empleado = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$empleado) {
            throw new Exception('Empleado no encontrado o inactivo');
        }

        $select_pk = $col_pk_asist ? qi($col_pk_asist) . " AS registro_id" : "NULL AS registro_id";
        $select_salida = $col_hora_salida_asist ? qi($col_hora_salida_asist) . " AS hora_salida" : "NULL AS hora_salida";
        $filtro_fecha = null;
        if ($col_fecha_asist) {
            $filtro_fecha = qi($col_fecha_asist) . " = CURDATE()";
        } elseif ($col_fecha_creacion_asist) {
            $filtro_fecha = "DATE(" . qi($col_fecha_creacion_asist) . ") = CURDATE()";
        }

        $asistencia_existente = null;
        if ($filtro_fecha) {
            $stmt = $pdo->prepare("SELECT {$select_pk}, {$select_salida} FROM asistencias WHERE " . qi($col_empleado_asist) . " = ? AND {$filtro_fecha} LIMIT 1");
            $stmt->execute([$empleado_id]);
            $asistencia_existente = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        $hora_actual = date('H:i:s');

        if ($asistencia_existente && empty($asistencia_existente['hora_salida'])) {
            if (!$col_hora_salida_asist) {
                throw new Exception('No existe columna de hora de salida en asistencias');
            }

            if (!empty($asistencia_existente['registro_id']) && $col_pk_asist) {
                $stmt = $pdo->prepare("UPDATE asistencias SET " . qi($col_hora_salida_asist) . " = ? WHERE " . qi($col_pk_asist) . " = ?");
                $stmt->execute([$hora_actual, $asistencia_existente['registro_id']]);
            } else {
                $whereSalida = $filtro_fecha ?: "1=1";
                $stmt = $pdo->prepare("UPDATE asistencias SET " . qi($col_hora_salida_asist) . " = ? WHERE " . qi($col_empleado_asist) . " = ? AND {$whereSalida} LIMIT 1");
                $stmt->execute([$hora_actual, $empleado_id]);
            }

            echo json_encode([
                'success' => true,
                'message' => '✓ Salida registrada correctamente',
                'empleado' => $empleado,
                'tipo' => 'asistencia',
                'subtipo' => 'salida',
                'hora_salida' => date('H:i', strtotime($hora_actual))
            ]);
            exit;
        } elseif ($asistencia_existente && !empty($asistencia_existente['hora_salida'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Este empleado ya completó su jornada hoy',
                'empleado' => $empleado
            ]);
            exit;
        }

        $stmt = $pdo->prepare("SELECT
            COALESCE(ehd.hora_entrada, eh.hora_entrada) as hora_entrada,
            COALESCE(ehd.hora_salida, eh.hora_salida) as hora_salida,
            COALESCE(ehd.tolerancia_minutos, eh.tolerancia_minutos, 10) as tolerancia_minutos
        FROM empleados e
        LEFT JOIN empleados_horarios eh ON e.id = eh.empleado_id AND eh.activo = 1
        LEFT JOIN empleados_horarios_dias ehd ON e.id = ehd.empleado_id
            AND ehd.dia_semana = DAYOFWEEK(CURDATE()) - 1
            AND ehd.activo = 1
        WHERE e.id = ?");
        $stmt->execute([$empleado_id]);
        $horario = $stmt->fetch(PDO::FETCH_ASSOC);

        $estado = 'presente';
        if ($horario && $horario['hora_entrada']) {
            $hora_entrada_esperada = strtotime($horario['hora_entrada']);
            $hora_entrada_real = strtotime($hora_actual);
            $tolerancia_segundos = ($horario['tolerancia_minutos'] ?? 10) * 60;
            if ($hora_entrada_real > ($hora_entrada_esperada + $tolerancia_segundos)) {
                $estado = 'tarde';
            }
        }

        $insertCols = [qi($col_empleado_asist)];
        $insertVals = ['?'];
        $insertParams = [$empleado_id];

        if ($col_fecha_asist) {
            $insertCols[] = qi($col_fecha_asist);
            $insertVals[] = 'CURDATE()';
        }
        if ($col_hora_entrada_asist) {
            $insertCols[] = qi($col_hora_entrada_asist);
            $insertVals[] = '?';
            $insertParams[] = $hora_actual;
        }
        if ($col_estado_asist) {
            $insertCols[] = qi($col_estado_asist);
            $insertVals[] = '?';
            $insertParams[] = $estado;
        }
        if ($col_creado_por_asist) {
            $insertCols[] = qi($col_creado_por_asist);
            $insertVals[] = '?';
            $insertParams[] = $usuario_id;
        }
        if ($col_fecha_creacion_asist) {
            $insertCols[] = qi($col_fecha_creacion_asist);
            $insertVals[] = 'NOW()';
        }

        if (count($insertCols) < 2) {
            throw new Exception('Estructura de asistencias incompleta para registrar entrada');
        }

        $stmt = $pdo->prepare("INSERT INTO asistencias (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', $insertVals) . ")");
        $stmt->execute($insertParams);

        $mensaje = $estado === 'presente' ? '✓ Entrada registrada correctamente' : '⚠ Entrada tardía registrada';
        echo json_encode([
            'success' => true,
            'message' => $mensaje,
            'empleado' => $empleado,
            'hora_entrada' => date('H:i', strtotime($hora_actual)),
            'tipo' => 'asistencia',
            'subtipo' => 'entrada',
            'estado' => $estado
        ]);
        exit;
    }

    // 2) Producción - buscar item por barcode
    $stmt = $pdo->prepare("SELECT 
                pib.*, pi.producto_id, pr.nombre as producto_nombre, op.pedido_id, p.numero_pedido,
                u_inicio.nombre as usuario_inicio_nombre, u_termino.nombre as usuario_termino_nombre
            FROM ecommerce_produccion_items_barcode pib
            JOIN ecommerce_pedido_items pi ON pib.pedido_item_id = pi.id
            JOIN ecommerce_productos pr ON pi.producto_id = pr.id
            JOIN ecommerce_ordenes_produccion op ON pib.orden_produccion_id = op.id
            JOIN ecommerce_pedidos p ON op.pedido_id = p.id
            LEFT JOIN usuarios u_inicio ON pib.usuario_inicio = u_inicio.id
            LEFT JOIN usuarios u_termino ON pib.usuario_termino = u_termino.id
            WHERE pib.codigo_barcode = ?");
    $stmt->execute([$codigo]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($item) {
        ensure_produccion_scans_schema($pdo);

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT id, estado, orden_produccion_id FROM ecommerce_produccion_items_barcode WHERE id = ? FOR UPDATE");
            $stmt->execute([(int)$item['id']]);
            $item_lock = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$item_lock) {
                throw new Exception('Item de producción no encontrado');
            }

            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM ecommerce_produccion_scans WHERE produccion_item_id = ?");
            $stmt->execute([(int)$item['id']]);
            $total_scans = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

            if ($total_scans >= 3 || (string)$item_lock['estado'] === (string)$estado_terminado) {
                $pdo->commit();
                echo json_encode([
                    'success' => true,
                    'tipo' => 'produccion',
                    'message' => 'Este item ya está terminado',
                    'item' => $item,
                    'etapa' => 'terminado',
                    'auto' => true
                ]);
                exit;
            }

            $etapa = $total_scans === 0 ? 'corte' : ($total_scans === 1 ? 'armado' : 'terminado');

            if ($etapa === 'corte') {
                $stmt = $pdo->prepare("UPDATE ecommerce_produccion_items_barcode
                    SET estado = ?,
                        usuario_inicio = COALESCE(usuario_inicio, ?),
                        fecha_inicio = COALESCE(fecha_inicio, NOW())
                    WHERE id = ?");
                $stmt->execute([$estado_corte, $usuario_id, (int)$item['id']]);
                $mensaje = '🪚 Corte registrado';
            } elseif ($etapa === 'armado') {
                $stmt = $pdo->prepare("UPDATE ecommerce_produccion_items_barcode
                    SET estado = ?
                    WHERE id = ?");
                $stmt->execute([$estado_armado, (int)$item['id']]);
                $mensaje = '🧩 Armado registrado';
            } else {
                $stmt = $pdo->prepare("UPDATE ecommerce_produccion_items_barcode
                    SET estado = ?, usuario_termino = ?, fecha_termino = NOW()
                    WHERE id = ?");
                $stmt->execute([$estado_terminado, $usuario_id, (int)$item['id']]);

                $stmt = $pdo->prepare("SELECT COUNT(*) as total,
                    SUM(CASE WHEN estado = ? THEN 1 ELSE 0 END) as terminados
                    FROM ecommerce_produccion_items_barcode
                    WHERE orden_produccion_id = ?");
                $stmt->execute([$estado_terminado, (int)$item_lock['orden_produccion_id']]);
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                if ((int)($stats['total'] ?? 0) > 0 && (int)($stats['total'] ?? 0) === (int)($stats['terminados'] ?? 0)) {
                    $stmt = $pdo->prepare("UPDATE ecommerce_ordenes_produccion SET estado = 'terminado' WHERE id = ?");
                    $stmt->execute([(int)$item_lock['orden_produccion_id']]);
                }

                $mensaje = '✅ Item terminado';
            }

            $stmt = $pdo->prepare("INSERT INTO ecommerce_produccion_scans
                (produccion_item_id, orden_produccion_id, pedido_id, usuario_id, etapa)
                VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                (int)$item['id'],
                (int)$item_lock['orden_produccion_id'],
                (int)$item['pedido_id'],
                (int)$usuario_id,
                $etapa
            ]);

            $stmt = $pdo->prepare("SELECT 
                    pib.*, pi.producto_id, pr.nombre as producto_nombre, op.pedido_id, p.numero_pedido,
                    u_inicio.nombre as usuario_inicio_nombre, u_termino.nombre as usuario_termino_nombre
                FROM ecommerce_produccion_items_barcode pib
                JOIN ecommerce_pedido_items pi ON pib.pedido_item_id = pi.id
                JOIN ecommerce_productos pr ON pi.producto_id = pr.id
                JOIN ecommerce_ordenes_produccion op ON pib.orden_produccion_id = op.id
                JOIN ecommerce_pedidos p ON op.pedido_id = p.id
                LEFT JOIN usuarios u_inicio ON pib.usuario_inicio = u_inicio.id
                LEFT JOIN usuarios u_termino ON pib.usuario_termino = u_termino.id
                WHERE pib.id = ?");
            $stmt->execute([(int)$item['id']]);
            $item_actualizado = $stmt->fetch(PDO::FETCH_ASSOC) ?: $item;

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'tipo' => 'produccion',
                'message' => $mensaje,
                'item' => $item_actualizado,
                'etapa' => $etapa,
                'auto' => true,
                'escaneos' => $total_scans + 1
            ]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        exit;
    }

    // 3) Código de pedido/orden de producción (ej: PED-COT-...)
    if (table_exists($pdo, 'ecommerce_pedidos')) {
        $col_numero_pedido = first_existing_column($pdo, 'ecommerce_pedidos', ['numero_pedido', 'codigo', 'numero']);
        if ($col_numero_pedido) {
            $col_estado_pedido = first_existing_column($pdo, 'ecommerce_pedidos', ['estado']);

            $select_estado_pedido = $col_estado_pedido
                ? 'p.' . qi($col_estado_pedido) . ' AS estado_pedido'
                : 'NULL AS estado_pedido';

            $sqlPedido = "SELECT
                    p.id AS pedido_id,
                    p." . qi($col_numero_pedido) . " AS numero_pedido,
                    {$select_estado_pedido}
                FROM ecommerce_pedidos p
                WHERE UPPER(TRIM(p." . qi($col_numero_pedido) . ")) = ?
                   OR UPPER(TRIM(p." . qi($col_numero_pedido) . ")) = ?
                   OR UPPER(TRIM(p." . qi($col_numero_pedido) . ")) LIKE ?
                LIMIT 1";

            $stmt = $pdo->prepare($sqlPedido);
            $stmt->execute([
                $codigo,
                $codigo_limpio,
                '%' . $codigo_solo_alnum_guion . '%'
            ]);
            $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($pedido) {
                $orden = null;
                if (table_exists($pdo, 'ecommerce_ordenes_produccion')) {
                    $col_op_estado = first_existing_column($pdo, 'ecommerce_ordenes_produccion', ['estado']);
                    $select_op_estado = $col_op_estado ? qi($col_op_estado) . ' AS estado_orden' : 'NULL AS estado_orden';

                    $stmt = $pdo->prepare("SELECT id AS orden_id, {$select_op_estado} FROM ecommerce_ordenes_produccion WHERE pedido_id = ? LIMIT 1");
                    $stmt->execute([(int)$pedido['pedido_id']]);
                    $orden = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
                }

                echo json_encode([
                    'success' => true,
                    'tipo' => 'detalle',
                    'message' => 'Pedido reconocido',
                    'detalle' => [
                        'codigo' => $codigo,
                        'pedido_id' => (int)$pedido['pedido_id'],
                        'numero_pedido' => $pedido['numero_pedido'],
                        'estado_pedido' => $pedido['estado_pedido'] ?? null,
                        'orden_produccion_id' => $orden['orden_id'] ?? null,
                        'estado_orden_produccion' => $orden['estado_orden'] ?? null
                    ]
                ]);
                exit;
            }
        }
    }

    // 3.b) Código de cotización (fallback)
    if (table_exists($pdo, 'ecommerce_cotizaciones') && column_exists($pdo, 'ecommerce_cotizaciones', 'numero_cotizacion')) {
        $stmt = $pdo->prepare("SELECT id, numero_cotizacion FROM ecommerce_cotizaciones
            WHERE UPPER(TRIM(numero_cotizacion)) = ?
               OR UPPER(TRIM(numero_cotizacion)) = ?
               OR UPPER(TRIM(numero_cotizacion)) LIKE ?
            LIMIT 1");
        $stmt->execute([
            $codigo,
            $codigo_limpio,
            '%' . $codigo_solo_alnum_guion . '%'
        ]);
        $cot = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($cot) {
            echo json_encode([
                'success' => true,
                'tipo' => 'detalle',
                'message' => 'Cotización reconocida',
                'detalle' => [
                    'codigo' => $codigo,
                    'cotizacion_id' => (int)$cot['id'],
                    'numero_cotizacion' => $cot['numero_cotizacion']
                ]
            ]);
            exit;
        }
    }

    // 4) Detalle genérico (compatible con distintos esquemas)
    if (table_exists($pdo, 'ecommerce_pedido_items')) {
        $col_codigo_item = first_existing_column(
            $pdo,
            'ecommerce_pedido_items',
            ['codigo_barcode', 'codigo_barra', 'barcode', 'codigo']
        );

        if ($col_codigo_item) {
            $stmt = $pdo->prepare("SELECT * FROM ecommerce_pedido_items WHERE " . qi($col_codigo_item) . " = ?");
            $stmt->execute([$codigo]);
            $detalle = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($detalle) {
                echo json_encode(['success'=>true,'tipo'=>'detalle','detalle'=>$detalle]);
                exit;
            }
        }
    }

    // 5) Entrega de producto (tabla productos)
    $stmt = $pdo->prepare("SELECT id, estado_id FROM productos WHERE codigo_barra = ?");
    $stmt->execute([$codigo]);
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($producto) {
        $pdo->prepare("UPDATE productos SET estado_id = 4 WHERE id = ?")->execute([$producto['id']]);
        $pdo->prepare("INSERT INTO historial_estados (producto_id, estado_id) VALUES (?, 4)")->execute([$producto['id']]);
        echo json_encode(['success'=>true,'tipo'=>'entrega','message'=>'✅ Producto ENTREGADO correctamente']);
        exit;
    }

    throw new Exception('Código no reconocido en ningún módulo');

} catch (Throwable $e) {
    // Para errores de proceso devolvemos 200 y success=false,
    // así el frontend no corta el flujo por HTTP 4xx.
    http_response_code(200);
    if ($e instanceof PDOException) {
        error_log('scan_api SQL error: ' . $e->getMessage());
        echo json_encode([
            'success'=>false,
            'message'=>'Error SQL al procesar el escaneo',
            'sql_error'=>$e->getMessage(),
            'sql_state'=>$e->getCode()
        ]);
    } else {
        echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
    }
}
