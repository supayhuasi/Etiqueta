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
    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function column_exists(PDO $pdo, string $table, string $column): bool {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
        return false;
    }
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
    $stmt->execute([$column]);
    return (bool)$stmt->fetchColumn();
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
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    if (!is_array($data)) {
        throw new Exception('JSON inválido');
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
                if ($item['estado'] !== 'en_corte') {
                    throw new Exception('Este item ya fue iniciado');
                }
                $stmt = $pdo->prepare("UPDATE ecommerce_produccion_items_barcode \
                    SET estado = 'armado', usuario_inicio = ?, fecha_inicio = NOW() WHERE id = ?");
                $stmt->execute([$usuario_id, $item_id]);
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
                if ($item['estado'] !== 'armado') {
                    throw new Exception('Este item no está en armado');
                }
                $stmt = $pdo->prepare("UPDATE ecommerce_produccion_items_barcode \
                    SET estado = 'terminado', usuario_termino = ?, fecha_termino = NOW() WHERE id = ?");
                $stmt->execute([$usuario_id, $item_id]);
                $stmt = $pdo->prepare("INSERT INTO ecommerce_produccion_scans
                    (produccion_item_id, orden_produccion_id, pedido_id, usuario_id, etapa)
                    SELECT pib.id, pib.orden_produccion_id, op.pedido_id, ?, 'terminado'
                    FROM ecommerce_produccion_items_barcode pib
                    JOIN ecommerce_ordenes_produccion op ON op.id = pib.orden_produccion_id
                    WHERE pib.id = ?");
                $stmt->execute([$usuario_id, $item_id]);

                // verificar si la orden completa se terminó
                $stmt = $pdo->prepare("SELECT COUNT(*) as total, \
                       SUM(CASE WHEN estado = 'terminado' THEN 1 ELSE 0 END) as terminados \
                    FROM ecommerce_produccion_items_barcode \
                    WHERE orden_produccion_id = (
                        SELECT orden_produccion_id FROM ecommerce_produccion_items_barcode WHERE id = ?
                    )");
                $stmt->execute([$item_id]);
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);

                $mensaje = '✅ Item terminado correctamente';
                if ($stats['total'] == $stats['terminados']) {
                    $stmt = $pdo->prepare("UPDATE ecommerce_ordenes_produccion \
                        SET estado = 'terminado' \
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
                $stmt = $pdo->prepare("UPDATE ecommerce_produccion_items_barcode \
                    SET estado = 'rechazado', observaciones = ?, usuario_termino = ?, fecha_termino = NOW() \
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

    // 1) Asistencias - formato EMP000001
    if (preg_match('/^EMP(\d{1,6})$/', $codigo, $m)) {
        if (!in_array($rol, ['ventas','operario','admin'])) {
            throw new Exception('Sin permiso para registrar asistencias');
        }

        if (!table_exists($pdo, 'empleados') || !table_exists($pdo, 'asistencias')) {
            throw new Exception('El módulo de asistencias no está instalado correctamente');
        }

        $empleado_id = (int)$m[1];
        $asist_col_empleado = first_existing_column($pdo, 'asistencias', ['empleado_id', 'id_empleado', 'empleado', 'empleadoid']);
        $asist_col_pk = first_existing_column($pdo, 'asistencias', ['id', 'asistencia_id', 'id_asistencia']);
        $asist_col_fecha = first_existing_column($pdo, 'asistencias', ['fecha', 'fecha_asistencia', 'dia']);
        $asist_col_fecha_creacion = first_existing_column($pdo, 'asistencias', ['fecha_creacion', 'created_at']);
        $asist_col_hora_entrada = first_existing_column($pdo, 'asistencias', ['hora_entrada', 'hora_ingreso', 'entrada']);
        $asist_col_hora_salida = first_existing_column($pdo, 'asistencias', ['hora_salida', 'hora_egreso', 'salida']);
        $asist_col_estado = first_existing_column($pdo, 'asistencias', ['estado']);
        $asist_col_creado_por = first_existing_column($pdo, 'asistencias', ['creado_por', 'usuario_id']);

        if (!$asist_col_empleado) {
            throw new Exception('No se encontró la columna de empleado en asistencias');
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

        // verificar asistencia existente
        $asistencia_existente = null;
        $select_pk = $asist_col_pk ? qi($asist_col_pk) . " as registro_id" : "NULL as registro_id";
        $select_hora_entrada = $asist_col_hora_entrada ? qi($asist_col_hora_entrada) . " as hora_entrada" : "NULL as hora_entrada";
        $select_hora_salida = $asist_col_hora_salida ? qi($asist_col_hora_salida) . " as hora_salida" : "NULL as hora_salida";
        if ($asist_col_fecha) {
            $stmt = $pdo->prepare("SELECT {$select_pk}, {$select_hora_entrada}, {$select_hora_salida} FROM asistencias WHERE " . qi($asist_col_empleado) . " = ? AND " . qi($asist_col_fecha) . " = CURDATE() LIMIT 1");
            $stmt->execute([$empleado_id]);
            $asistencia_existente = $stmt->fetch(PDO::FETCH_ASSOC);
        } elseif ($asist_col_fecha_creacion) {
            $stmt = $pdo->prepare("SELECT {$select_pk}, {$select_hora_entrada}, {$select_hora_salida} FROM asistencias WHERE " . qi($asist_col_empleado) . " = ? AND DATE(" . qi($asist_col_fecha_creacion) . ") = CURDATE() LIMIT 1");
            $stmt->execute([$empleado_id]);
            $asistencia_existente = $stmt->fetch(PDO::FETCH_ASSOC);
        } elseif ($asist_col_hora_salida) {
            $stmt = $pdo->prepare("SELECT {$select_pk}, {$select_hora_entrada}, {$select_hora_salida} FROM asistencias WHERE " . qi($asist_col_empleado) . " = ? AND " . qi($asist_col_hora_salida) . " IS NULL ORDER BY " . qi($asist_col_fecha_creacion ?: $asist_col_pk ?: $asist_col_empleado) . " DESC LIMIT 1");
            $stmt->execute([$empleado_id]);
            $asistencia_existente = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        $hora_actual = date('H:i:s');
        if ($asistencia_existente && empty($asistencia_existente['hora_salida'])) {
            if (!$asist_col_hora_salida) {
                throw new Exception('La tabla asistencias no tiene columna hora_salida');
            }
            if (!empty($asistencia_existente['registro_id']) && $asist_col_pk) {
                $stmt = $pdo->prepare("UPDATE asistencias SET " . qi($asist_col_hora_salida) . " = ? WHERE " . qi($asist_col_pk) . " = ?");
                $stmt->execute([$hora_actual, $asistencia_existente['registro_id']]);
            } elseif ($asist_col_fecha) {
                $stmt = $pdo->prepare("UPDATE asistencias SET " . qi($asist_col_hora_salida) . " = ? WHERE " . qi($asist_col_empleado) . " = ? AND " . qi($asist_col_fecha) . " = CURDATE() AND (" . qi($asist_col_hora_salida) . " IS NULL OR " . qi($asist_col_hora_salida) . " = '00:00:00') LIMIT 1");
                $stmt->execute([$hora_actual, $empleado_id]);
            } elseif ($asist_col_fecha_creacion) {
                $stmt = $pdo->prepare("UPDATE asistencias SET " . qi($asist_col_hora_salida) . " = ? WHERE " . qi($asist_col_empleado) . " = ? AND DATE(" . qi($asist_col_fecha_creacion) . ") = CURDATE() AND (" . qi($asist_col_hora_salida) . " IS NULL OR " . qi($asist_col_hora_salida) . " = '00:00:00') LIMIT 1");
                $stmt->execute([$hora_actual, $empleado_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE asistencias SET " . qi($asist_col_hora_salida) . " = ? WHERE " . qi($asist_col_empleado) . " = ? AND (" . qi($asist_col_hora_salida) . " IS NULL OR " . qi($asist_col_hora_salida) . " = '00:00:00') LIMIT 1");
                $stmt->execute([$hora_actual, $empleado_id]);
            }
            echo json_encode([
                'success'=>true,
                'message'=>'✓ Salida registrada correctamente',
                'empleado'=>$empleado,
                'tipo'=>'asistencia',
                'subtipo'=>'salida',
                'hora_salida'=>date('H:i', strtotime($hora_actual))
            ]);
            exit;
        } elseif ($asistencia_existente && !empty($asistencia_existente['hora_salida'])) {
            echo json_encode([
                'success'=>false,
                'message'=>'Este empleado ya completó su jornada hoy',
                'empleado'=>$empleado
            ]);
            exit;
        }
        // determinar estado
        $horario = null;
        if (table_exists($pdo, 'empleados_horarios') && table_exists($pdo, 'empleados_horarios_dias')) {
            try {
                $stmt = $pdo->prepare("SELECT \
                    COALESCE(ehd.hora_entrada, eh.hora_entrada) as hora_entrada, \
                    COALESCE(ehd.hora_salida, eh.hora_salida) as hora_salida, \
                    COALESCE(ehd.tolerancia_minutos, eh.tolerancia_minutos, 10) as tolerancia_minutos \
                FROM empleados e 
                LEFT JOIN empleados_horarios eh ON e.id = eh.empleado_id AND eh.activo = 1
                LEFT JOIN empleados_horarios_dias ehd ON e.id = ehd.empleado_id 
                    AND ehd.dia_semana = DAYOFWEEK(CURDATE()) - 1 
                    AND ehd.activo = 1
                WHERE e.id = ?");
                $stmt->execute([$empleado_id]);
                $horario = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $horario = null;
            }
        }

        $estado = 'presente';
        if ($horario && $horario['hora_entrada']) {
            $hora_entrada_esperada = strtotime($horario['hora_entrada']);
            $hora_entrada_real = strtotime($hora_actual);
            $tolerancia_segundos = ($horario['tolerancia_minutos'] ?? 10) * 60;
            if ($hora_entrada_real > ($hora_entrada_esperada + $tolerancia_segundos)) {
                $estado = 'tarde';
            }
        }

        $cols = [$asist_col_empleado];
        $vals = ['?'];
        $params = [$empleado_id];

        if ($asist_col_fecha) {
            $cols[] = $asist_col_fecha;
            $vals[] = 'CURDATE()';
        }
        if ($asist_col_hora_entrada) {
            $cols[] = $asist_col_hora_entrada;
            $vals[] = '?';
            $params[] = $hora_actual;
        }
        if ($asist_col_estado) {
            $cols[] = $asist_col_estado;
            $vals[] = '?';
            $params[] = $estado;
        }
        if ($asist_col_creado_por) {
            $cols[] = $asist_col_creado_por;
            $vals[] = '?';
            $params[] = $usuario_id;
        }
        if ($asist_col_fecha_creacion) {
            $cols[] = $asist_col_fecha_creacion;
            $vals[] = 'NOW()';
        }

        if (count($cols) < 2) {
            throw new Exception('Estructura de asistencias incompleta para registrar entrada');
        }

        $cols_sql = array_map('qi', $cols);
        $sql_insert = "INSERT INTO asistencias (" . implode(', ', $cols_sql) . ") VALUES (" . implode(', ', $vals) . ")";
        try {
            $stmt = $pdo->prepare($sql_insert);
            $stmt->execute($params);
        } catch (PDOException $insertError) {
            $meta = get_table_columns_map($pdo, 'asistencias');
            $colsSet = array_fill_keys($cols, true);

            foreach ($meta as $field => $colmeta) {
                if (isset($colsSet[$field])) {
                    continue;
                }

                $null = strtoupper((string)($colmeta['Null'] ?? 'YES'));
                $default = $colmeta['Default'] ?? null;
                $extra = strtolower((string)($colmeta['Extra'] ?? ''));
                $type = strtolower((string)($colmeta['Type'] ?? ''));

                if ($null === 'YES' || $default !== null || strpos($extra, 'auto_increment') !== false) {
                    continue;
                }

                if (preg_match('/(empleado|personal).*id/i', $field)) {
                    $cols[] = $field;
                    $vals[] = '?';
                    $params[] = $empleado_id;
                } elseif (preg_match('/(usuario|user|creado_por).*id/i', $field)) {
                    $cols[] = $field;
                    $vals[] = '?';
                    $params[] = $usuario_id;
                } elseif (strpos($type, 'datetime') !== false || strpos($type, 'timestamp') !== false) {
                    $cols[] = $field;
                    $vals[] = 'NOW()';
                } elseif (strpos($type, 'date') === 0) {
                    $cols[] = $field;
                    $vals[] = 'CURDATE()';
                } elseif (strpos($type, 'time') === 0) {
                    $cols[] = $field;
                    $vals[] = '?';
                    $params[] = $hora_actual;
                } elseif (strpos($type, 'enum(') === 0) {
                    $enumValues = [];
                    if (preg_match('/^enum\((.*)\)$/', $type, $mm)) {
                        $enumValues = str_getcsv($mm[1], ',', "'", "\\");
                    }
                    $cols[] = $field;
                    $vals[] = '?';
                    $params[] = $enumValues[0] ?? '';
                } elseif (preg_match('/^(tinyint|smallint|mediumint|int|bigint|decimal|float|double)/', $type)) {
                    $cols[] = $field;
                    $vals[] = '?';
                    $params[] = 0;
                }
            }

            $cols_sql = array_map('qi', $cols);
            $sql_insert_retry = "INSERT INTO asistencias (" . implode(', ', $cols_sql) . ") VALUES (" . implode(', ', $vals) . ")";
            $stmt = $pdo->prepare($sql_insert_retry);
            $stmt->execute($params);
        }

        $mensaje = $estado === 'presente' ? '✓ Entrada registrada correctamente' : '⚠ Entrada tardía registrada';
        echo json_encode([
            'success'=>true,
            'message'=>$mensaje,
            'empleado'=>$empleado,
            'hora_entrada'=>date('H:i', strtotime($hora_actual)),
            'tipo'=>'asistencia',
            'subtipo'=>'entrada',
            'estado'=>$estado
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

            if ($total_scans >= 3 || $item_lock['estado'] === 'terminado') {
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
                    SET estado = 'en_corte',
                        usuario_inicio = COALESCE(usuario_inicio, ?),
                        fecha_inicio = COALESCE(fecha_inicio, NOW())
                    WHERE id = ?");
                $stmt->execute([$usuario_id, (int)$item['id']]);
                $mensaje = '🪚 Corte registrado';
            } elseif ($etapa === 'armado') {
                $stmt = $pdo->prepare("UPDATE ecommerce_produccion_items_barcode
                    SET estado = 'armado'
                    WHERE id = ?");
                $stmt->execute([(int)$item['id']]);
                $mensaje = '🧩 Armado registrado';
            } else {
                $stmt = $pdo->prepare("UPDATE ecommerce_produccion_items_barcode
                    SET estado = 'terminado', usuario_termino = ?, fecha_termino = NOW()
                    WHERE id = ?");
                $stmt->execute([$usuario_id, (int)$item['id']]);

                $stmt = $pdo->prepare("SELECT COUNT(*) as total,
                    SUM(CASE WHEN estado = 'terminado' THEN 1 ELSE 0 END) as terminados
                    FROM ecommerce_produccion_items_barcode
                    WHERE orden_produccion_id = ?");
                $stmt->execute([(int)$item_lock['orden_produccion_id']]);
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

    // 3) Detalle genérico (se puede adaptar a la tabla que se use)
    $stmt = $pdo->prepare("SELECT * FROM ecommerce_pedido_items WHERE codigo_barcode = ?");
    $stmt->execute([$codigo]);
    $detalle = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($detalle) {
        echo json_encode(['success'=>true,'tipo'=>'detalle','detalle'=>$detalle]);
        exit;
    }

    // 4) Entrega de producto (tabla productos)
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
    http_response_code(resolve_http_status_from_exception($e));
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
