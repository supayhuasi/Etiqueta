<?php

if (!function_exists('produccion_asegurar_schema')) {
    function produccion_asegurar_schema(PDO $pdo): void
    {
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM ecommerce_pedido_items")->fetchAll(PDO::FETCH_COLUMN, 0);
        } catch (Throwable $e) {
            return;
        }
        if (!in_array('fotos_produccion', $cols, true)) {
            $pdo->exec("ALTER TABLE ecommerce_pedido_items ADD COLUMN fotos_produccion TEXT NULL");
        }
        if (!in_array('descripcion', $cols, true)) {
            $after = in_array('atributos', $cols, true) ? ' AFTER atributos' : '';
            $pdo->exec("ALTER TABLE ecommerce_pedido_items ADD COLUMN descripcion TEXT NULL{$after}");
        }
        $dir = produccion_dir_fotos();
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }
}

if (!function_exists('produccion_dir_fotos')) {
    function produccion_dir_fotos(): string
    {
        return dirname(__DIR__, 2) . '/uploads/produccion';
    }
}

if (!function_exists('produccion_foto_abs')) {
    function produccion_foto_abs(string $archivo): string
    {
        $archivo = basename($archivo);
        return produccion_dir_fotos() . '/' . $archivo;
    }
}

if (!function_exists('produccion_foto_url')) {
    function produccion_foto_url(string $archivo): string
    {
        return '../uploads/produccion/' . rawurlencode(basename($archivo));
    }
}

if (!function_exists('produccion_parse_fotos')) {
    function produccion_parse_fotos($raw): array
    {
        if (is_array($raw)) {
            return array_values(array_filter($raw, static function ($f) {
                return is_string($f) && $f !== '';
            }));
        }
        $raw = trim((string)$raw);
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach ($decoded as $item) {
            if (is_string($item) && $item !== '') {
                $out[] = $item;
            } elseif (is_array($item) && !empty($item['archivo'])) {
                $out[] = (string)$item['archivo'];
            }
        }
        return $out;
    }
}

if (!function_exists('produccion_parse_atributos')) {
    function produccion_parse_atributos($raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        $decoded = json_decode((string)$raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('produccion_guardar_fotos_item')) {
    function produccion_guardar_fotos_item(array $actuales, $filesCampo, int $itemId): array
    {
        if (!is_array($filesCampo) || empty($filesCampo['name'])) {
            return $actuales;
        }
        $nombres = $filesCampo['name'];
        $tmps = $filesCampo['tmp_name'] ?? [];
        $errores = $filesCampo['error'] ?? [];
        $sizes = $filesCampo['size'] ?? [];
        if (!is_array($nombres)) {
            $nombres = [$nombres];
            $tmps = [$tmps];
            $errores = [$errores];
            $sizes = [$sizes];
        }

        $dir = produccion_dir_fotos();
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $permitidos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
        foreach ($nombres as $i => $nombreOrig) {
            if (count($actuales) >= 8) {
                break;
            }
            $err = (int)($errores[$i] ?? UPLOAD_ERR_NO_FILE);
            if ($err === UPLOAD_ERR_NO_FILE || $nombreOrig === '') {
                continue;
            }
            if ($err !== UPLOAD_ERR_OK) {
                throw new Exception('No se pudo subir una foto del ítem.');
            }
            if ((int)($sizes[$i] ?? 0) > 6 * 1024 * 1024) {
                throw new Exception('Una foto supera los 6 MB.');
            }
            $tmp = (string)($tmps[$i] ?? '');
            $info = @getimagesize($tmp);
            $mime = is_array($info) ? (string)($info['mime'] ?? '') : '';
            if (!isset($permitidos[$mime])) {
                throw new Exception('Solo se aceptan JPG, PNG, GIF o WEBP.');
            }
            $archivo = 'op_' . $itemId . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $permitidos[$mime];
            if (!move_uploaded_file($tmp, $dir . '/' . $archivo)) {
                throw new Exception('No se pudo guardar la foto en el servidor.');
            }
            $actuales[] = $archivo;
        }
        return array_values($actuales);
    }
}

if (!function_exists('produccion_quitar_foto')) {
    function produccion_quitar_foto(array $actuales, string $archivo): array
    {
        $archivo = basename($archivo);
        $quedan = [];
        foreach ($actuales as $foto) {
            if ($foto === $archivo) {
                $path = produccion_foto_abs($foto);
                if (is_file($path)) {
                    @unlink($path);
                }
                continue;
            }
            $quedan[] = $foto;
        }
        return $quedan;
    }
}

if (!function_exists('produccion_guardar_items_desde_post')) {
    function produccion_guardar_items_desde_post(PDO $pdo, int $pedidoId, array $post, array $files): void
    {
        $itemsPost = $post['items'] ?? [];
        if (!is_array($itemsPost) || empty($itemsPost)) {
            return;
        }

        $stmt = $pdo->prepare("SELECT * FROM ecommerce_pedido_items WHERE id = ? AND pedido_id = ?");
        $upd = $pdo->prepare("
            UPDATE ecommerce_pedido_items
            SET ancho_cm = ?, alto_cm = ?, atributos = ?, descripcion = ?, fotos_produccion = ?
            WHERE id = ? AND pedido_id = ?
        ");

        $fotosFiles = $files['fotos'] ?? [];

        foreach ($itemsPost as $itemId => $datos) {
            $itemId = (int)$itemId;
            if ($itemId <= 0 || !is_array($datos)) {
                continue;
            }
            $stmt->execute([$itemId, $pedidoId]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$item) {
                continue;
            }

            $ancho = isset($datos['ancho_cm']) && $datos['ancho_cm'] !== '' ? (float)str_replace(',', '.', (string)$datos['ancho_cm']) : null;
            $alto = isset($datos['alto_cm']) && $datos['alto_cm'] !== '' ? (float)str_replace(',', '.', (string)$datos['alto_cm']) : null;
            $descripcion = trim((string)($datos['descripcion'] ?? ''));

            $nombres = $datos['attr_nombre'] ?? [];
            $valores = $datos['attr_valor'] ?? [];
            if (!is_array($nombres)) {
                $nombres = [];
            }
            if (!is_array($valores)) {
                $valores = [];
            }
            $previos = produccion_parse_atributos($item['atributos'] ?? '');
            $atributos = [];
            $max = max(count($nombres), count($valores));
            for ($i = 0; $i < $max; $i++) {
                $nombre = trim((string)($nombres[$i] ?? ''));
                $valor = trim((string)($valores[$i] ?? ''));
                if ($nombre === '' && $valor === '') {
                    continue;
                }
                $base = $previos[$i] ?? [];
                if (!is_array($base)) {
                    $base = [];
                }
                $base['nombre'] = $nombre !== '' ? $nombre : (string)($base['nombre'] ?? 'Atributo');
                $base['valor'] = $valor;
                $atributos[] = $base;
            }

            $fotos = produccion_parse_fotos($item['fotos_produccion'] ?? '');
            $campoFotos = [
                'name' => $fotosFiles['name'][$itemId] ?? [],
                'tmp_name' => $fotosFiles['tmp_name'][$itemId] ?? [],
                'error' => $fotosFiles['error'][$itemId] ?? [],
                'size' => $fotosFiles['size'][$itemId] ?? [],
            ];
            $fotos = produccion_guardar_fotos_item($fotos, $campoFotos, $itemId);

            $upd->execute([
                $ancho,
                $alto,
                !empty($atributos) ? json_encode($atributos, JSON_UNESCAPED_UNICODE) : null,
                $descripcion !== '' ? $descripcion : null,
                !empty($fotos) ? json_encode($fotos, JSON_UNESCAPED_UNICODE) : null,
                $itemId,
                $pedidoId,
            ]);
        }
    }
}

if (!function_exists('produccion_eliminar_foto_item')) {
    function produccion_eliminar_foto_item(PDO $pdo, int $pedidoId, int $itemId, string $archivo): void
    {
        $stmt = $pdo->prepare("SELECT fotos_produccion FROM ecommerce_pedido_items WHERE id = ? AND pedido_id = ?");
        $stmt->execute([$itemId, $pedidoId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new Exception('Ítem no encontrado.');
        }
        $fotos = produccion_quitar_foto(produccion_parse_fotos($row['fotos_produccion'] ?? ''), $archivo);
        $pdo->prepare("UPDATE ecommerce_pedido_items SET fotos_produccion = ? WHERE id = ? AND pedido_id = ?")
            ->execute([!empty($fotos) ? json_encode($fotos, JSON_UNESCAPED_UNICODE) : null, $itemId, $pedidoId]);
    }
}

if (!function_exists('produccion_pedidos_sin_orden')) {
    function produccion_pedidos_sin_orden(PDO $pdo, string $buscar = ''): array
    {
        $params = [];
        $where = "COALESCE(p.estado, '') <> 'cancelado'";
        if (trim($buscar) !== '') {
            $where .= " AND (p.numero_pedido LIKE ? OR c.nombre LIKE ?)";
            $like = '%' . trim($buscar) . '%';
            $params = [$like, $like];
        }

        $sql = "
            SELECT p.id, p.numero_pedido, p.estado, p.fecha_creacion, p.total, c.nombre AS cliente_nombre
            FROM ecommerce_pedidos p
            LEFT JOIN ecommerce_clientes c ON c.id = p.cliente_id
            LEFT JOIN ecommerce_ordenes_produccion op
                ON op.pedido_id = p.id AND COALESCE(op.estado, '') <> 'cancelado'
            WHERE {$where}
              AND op.id IS NULL
            ORDER BY p.id DESC
            LIMIT 80
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('produccion_cargar_items')) {
    function produccion_cargar_items(PDO $pdo, int $pedidoId): array
    {
        $stmt = $pdo->prepare("
            SELECT pi.*, pr.nombre AS producto_nombre
            FROM ecommerce_pedido_items pi
            LEFT JOIN ecommerce_productos pr ON pr.id = pi.producto_id
            WHERE pi.pedido_id = ?
            ORDER BY pi.id
        ");
        $stmt->execute([$pedidoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('produccion_crear_orden')) {
    function produccion_crear_orden(PDO $pdo, int $pedidoId, ?string $notas, ?string $fechaEntrega, ?int $usuarioId): int
    {
        $stmt = $pdo->prepare("SELECT * FROM ecommerce_ordenes_produccion WHERE pedido_id = ? LIMIT 1");
        $stmt->execute([$pedidoId]);
        $existente = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existente && strtolower((string)$existente['estado']) !== 'cancelado') {
            throw new Exception('Este pedido ya tiene una orden de producción.');
        }

        $notas = $notas !== null && trim($notas) !== '' ? trim($notas) : null;
        if ($fechaEntrega !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaEntrega)) {
            $fechaEntrega = null;
        }

        if ($existente) {
            $pdo->prepare("
                UPDATE ecommerce_ordenes_produccion
                SET estado = 'pendiente', notas = ?, fecha_entrega = ?, materiales_descontados = 0
                WHERE id = ?
            ")->execute([$notas, $fechaEntrega, (int)$existente['id']]);
            $ordenId = (int)$existente['id'];
        } else {
            $pdo->prepare("
                INSERT INTO ecommerce_ordenes_produccion (pedido_id, estado, notas, fecha_entrega, materiales_descontados)
                VALUES (?, 'pendiente', ?, ?, 0)
            ")->execute([$pedidoId, $notas, $fechaEntrega]);
            $ordenId = (int)$pdo->lastInsertId();
        }

        if ($ordenId <= 0) {
            throw new Exception('No se pudo crear la orden de producción.');
        }

        produccion_descontar_materiales($pdo, $pedidoId, $ordenId, $usuarioId);
        return $ordenId;
    }
}

if (!function_exists('produccion_descontar_materiales')) {
    function produccion_descontar_materiales(PDO $pdo, int $pedidoId, int $ordenId, ?int $usuarioId): void
    {
        if (!function_exists('obtener_receta_con_condiciones')) {
            return;
        }

        $stmt = $pdo->prepare("
            SELECT pi.*, p.usa_receta
            FROM ecommerce_pedido_items pi
            JOIN ecommerce_productos p ON pi.producto_id = p.id
            WHERE pi.pedido_id = ?
        ");
        $stmt->execute([$pedidoId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($items as $it) {
            if (empty($it['usa_receta'])) {
                continue;
            }
            $atributos = produccion_parse_atributos($it['atributos'] ?? '');
            $ancho = (float)($it['ancho_cm'] ?? 0);
            $alto = (float)($it['alto_cm'] ?? 0);
            $recetas = obtener_receta_con_condiciones($pdo, (int)$it['producto_id'], $ancho, $alto, $atributos);
            if (empty($recetas)) {
                continue;
            }
            $altoM = $alto / 100;
            $anchoM = $ancho / 100;
            $area = $altoM * $anchoM;

            foreach ($recetas as $r) {
                $factor = (float)$r['factor'];
                $merma = (float)$r['merma_pct'];
                $base = 0;
                if ($r['tipo_calculo'] === 'fijo') {
                    $base = $factor;
                } elseif ($r['tipo_calculo'] === 'por_area') {
                    $base = $area * $factor;
                } elseif ($r['tipo_calculo'] === 'por_ancho') {
                    $base = $anchoM * $factor;
                } elseif ($r['tipo_calculo'] === 'por_alto') {
                    $base = $altoM * $factor;
                }
                $cantidad = $base * (1 + ($merma / 100)) * (int)$it['cantidad'];
                $matId = (int)$r['material_producto_id'];

                $stmtStock = $pdo->prepare("SELECT stock FROM ecommerce_productos WHERE id = ?");
                $stmtStock->execute([$matId]);
                $stockAnterior = (float)($stmtStock->fetchColumn() ?: 0);
                $stockNuevo = $stockAnterior - $cantidad;
                $pdo->prepare("UPDATE ecommerce_productos SET stock = stock - ? WHERE id = ?")->execute([$cantidad, $matId]);

                if (function_exists('registrarMovimientoInventario')) {
                    registrarMovimientoInventario($pdo, [
                        'producto_id' => $matId,
                        'tipo' => 'produccion',
                        'tipo_item' => 'producto',
                        'item_id' => $matId,
                        'tipo_movimiento' => 'produccion',
                        'cantidad' => $cantidad,
                        'stock_anterior' => $stockAnterior,
                        'stock_nuevo' => $stockNuevo,
                        'referencia' => 'Orden-' . $ordenId,
                        'usuario_id' => $usuarioId,
                        'pedido_id' => $pedidoId,
                        'orden_produccion_id' => $ordenId,
                    ]);
                }
            }
        }

        $pdo->prepare("UPDATE ecommerce_ordenes_produccion SET materiales_descontados = 1 WHERE id = ?")->execute([$ordenId]);
    }
}

if (!function_exists('produccion_render_editor_items')) {
    /**
     * @param list<array<string,mixed>> $items
     */
    function produccion_render_editor_items(array $items): void
    {
        foreach ($items as $item):
            $itemId = (int)$item['id'];
            $atributos = produccion_parse_atributos($item['atributos'] ?? '');
            $fotos = produccion_parse_fotos($item['fotos_produccion'] ?? '');
            if (empty($atributos)) {
                $atributos = [['nombre' => 'Color', 'valor' => '']];
            }
            ?>
            <div class="card mb-3">
                <div class="card-header py-2">
                    <strong><?= htmlspecialchars((string)($item['producto_nombre'] ?? 'Producto')) ?></strong>
                    <span class="text-muted">× <?= htmlspecialchars((string)($item['cantidad'] ?? 1)) ?></span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6 col-md-3">
                            <label class="form-label">Ancho (cm)</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="items[<?= $itemId ?>][ancho_cm]" value="<?= htmlspecialchars((string)($item['ancho_cm'] ?? '')) ?>">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label">Alto (cm)</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="items[<?= $itemId ?>][alto_cm]" value="<?= htmlspecialchars((string)($item['alto_cm'] ?? '')) ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Observaciones del ítem</label>
                            <input type="text" class="form-control" name="items[<?= $itemId ?>][descripcion]" value="<?= htmlspecialchars((string)($item['descripcion'] ?? '')) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Colores / atributos</label>
                            <?php foreach ($atributos as $attr): ?>
                                <div class="row g-2 mb-2">
                                    <div class="col-5">
                                        <input type="text" class="form-control" name="items[<?= $itemId ?>][attr_nombre][]" value="<?= htmlspecialchars((string)($attr['nombre'] ?? '')) ?>" placeholder="Color, tela...">
                                    </div>
                                    <div class="col-7">
                                        <input type="text" class="form-control" name="items[<?= $itemId ?>][attr_valor][]" value="<?= htmlspecialchars((string)($attr['valor'] ?? '')) ?>" placeholder="Valor">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="row g-2">
                                <div class="col-5">
                                    <input type="text" class="form-control" name="items[<?= $itemId ?>][attr_nombre][]" placeholder="Agregar atributo">
                                </div>
                                <div class="col-7">
                                    <input type="text" class="form-control" name="items[<?= $itemId ?>][attr_valor][]" placeholder="Valor">
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Fotos para el PDF</label>
                            <?php if (!empty($fotos)): ?>
                                <div class="d-flex flex-wrap gap-2 mb-2">
                                    <?php foreach ($fotos as $foto): ?>
                                        <div class="border rounded p-1 text-center" style="width:110px;">
                                            <img src="<?= htmlspecialchars(produccion_foto_url($foto)) ?>" alt="" style="width:100px;height:72px;object-fit:cover;">
                                            <button type="submit" class="btn btn-link btn-sm text-danger p-0" name="eliminar_foto" value="<?= $itemId ?>|<?= htmlspecialchars($foto) ?>" onclick="return confirm('¿Quitar esta foto?');">Quitar</button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <input type="file" class="form-control" name="fotos[<?= $itemId ?>][]" accept="image/*" multiple>
                            <div class="form-text">JPG, PNG, GIF o WEBP. Salen en el PDF de la orden.</div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        endforeach;
    }
}
