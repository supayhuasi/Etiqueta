<?php

if (!function_exists('cc_asegurar_tablas')) {
    function cc_asegurar_tablas(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS ecommerce_cuenta_corriente (
                id INT PRIMARY KEY AUTO_INCREMENT,
                cliente_id INT NOT NULL,
                tipo ENUM('ingreso','aplicacion') NOT NULL,
                monto DECIMAL(12,2) NOT NULL,
                metodo VARCHAR(100) NULL,
                referencia VARCHAR(150) NULL,
                notas TEXT NULL,
                numero_recibo VARCHAR(30) NULL,
                pedido_id INT NULL,
                pago_pedido_id INT NULL,
                fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                creado_por INT NULL,
                INDEX idx_cc_cliente (cliente_id),
                INDEX idx_cc_fecha (fecha),
                INDEX idx_cc_recibo (numero_recibo),
                INDEX idx_cc_pedido (pedido_id)
            )
        ");
    }
}

if (!function_exists('cc_fmt_money')) {
    function cc_fmt_money(float $valor): string
    {
        return '$' . number_format($valor, 2, ',', '.');
    }
}

if (!function_exists('cc_saldo')) {
    function cc_saldo(PDO $pdo, int $clienteId): float
    {
        if ($clienteId <= 0) {
            return 0.0;
        }
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(CASE WHEN tipo = 'ingreso' THEN monto WHEN tipo = 'aplicacion' THEN -monto ELSE 0 END), 0)
            FROM ecommerce_cuenta_corriente
            WHERE cliente_id = ?
        ");
        $stmt->execute([$clienteId]);
        return round((float)$stmt->fetchColumn(), 2);
    }
}

if (!function_exists('cc_siguiente_recibo')) {
    function cc_siguiente_recibo(PDO $pdo): string
    {
        $ultimo = 0;
        try {
            $valor = $pdo->query("SELECT numero_recibo FROM ecommerce_cuenta_corriente WHERE numero_recibo LIKE 'REC-CC-%' ORDER BY id DESC LIMIT 1")->fetchColumn();
            if (is_string($valor) && preg_match('/(\d+)$/', $valor, $m)) {
                $ultimo = (int)$m[1];
            }
        } catch (Throwable $e) {
            $ultimo = 0;
        }
        return 'REC-CC-' . str_pad((string)($ultimo + 1), 6, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('cc_buscar_cliente')) {
    function cc_buscar_cliente(PDO $pdo, string $email, string $telefono, string $nombre): ?array
    {
        $email = trim($email);
        $telefono = preg_replace('/\s+/', '', $telefono);
        $nombre = trim($nombre);

        if ($email !== '') {
            $stmt = $pdo->prepare('SELECT * FROM ecommerce_clientes WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }
        }
        if ($telefono !== '') {
            $stmt = $pdo->prepare('SELECT * FROM ecommerce_clientes WHERE REPLACE(telefono, " ", "") = ? LIMIT 1');
            $stmt->execute([$telefono]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }
        }
        if ($nombre !== '') {
            $stmt = $pdo->prepare('SELECT * FROM ecommerce_clientes WHERE nombre = ? LIMIT 1');
            $stmt->execute([$nombre]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }
        }
        return null;
    }
}

if (!function_exists('cc_crear_cliente')) {
    function cc_crear_cliente(PDO $pdo, array $datos): int
    {
        $nombre = trim((string)($datos['nombre'] ?? ''));
        $email = trim((string)($datos['email'] ?? ''));
        $telefono = trim((string)($datos['telefono'] ?? ''));
        $direccion = trim((string)($datos['direccion'] ?? ''));
        if ($nombre === '') {
            throw new Exception('El nombre del cliente es obligatorio.');
        }

        $existente = cc_buscar_cliente($pdo, $email, $telefono, $nombre);
        if ($existente) {
            return (int)$existente['id'];
        }

        if ($email === '') {
            $email = 'cc-' . substr(bin2hex(random_bytes(6)), 0, 12) . '@sin-email.local';
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO ecommerce_clientes (nombre, email, telefono, direccion)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$nombre, $email, $telefono !== '' ? $telefono : null, $direccion !== '' ? $direccion : null]);
        } catch (Throwable $e) {
            $stmt = $pdo->prepare("INSERT INTO ecommerce_clientes (nombre, email, telefono) VALUES (?, ?, ?)");
            $stmt->execute([$nombre, $email, $telefono !== '' ? $telefono : null]);
        }

        $id = (int)$pdo->lastInsertId();
        if ($id <= 0) {
            throw new Exception('No se pudo crear el cliente.');
        }
        return $id;
    }
}

if (!function_exists('cc_registrar_ingreso')) {
    /**
     * @param list<array{cuenta_id:int,porcentaje:float,monto:float}> $partesCaja
     */
    function cc_registrar_ingreso(PDO $pdo, int $clienteId, float $monto, string $metodo, string $referencia, string $notas, string $fecha, ?int $usuarioId, array $partesCaja): array
    {
        $monto = round($monto, 2);
        if ($clienteId <= 0) {
            throw new Exception('Elegí un cliente.');
        }
        if ($monto <= 0) {
            throw new Exception('El monto debe ser mayor a 0.');
        }
        if (trim($metodo) === '') {
            throw new Exception('El método de pago es obligatorio.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $fecha = date('Y-m-d');
        }

        $ownTx = !$pdo->inTransaction();
        if ($ownTx) {
            $pdo->beginTransaction();
        }

        try {
            $recibo = cc_siguiente_recibo($pdo);
            $stmt = $pdo->prepare("
                INSERT INTO ecommerce_cuenta_corriente
                    (cliente_id, tipo, monto, metodo, referencia, notas, numero_recibo, fecha, creado_por)
                VALUES (?, 'ingreso', ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $clienteId,
                $monto,
                $metodo,
                $referencia !== '' ? $referencia : $recibo,
                $notas !== '' ? $notas : null,
                $recibo,
                $fecha . ' ' . date('H:i:s'),
                $usuarioId,
            ]);
            $movId = (int)$pdo->lastInsertId();

            if (!empty($partesCaja) && function_exists('cuentas_reparto_insertar_ingresos')) {
                cuentas_reparto_insertar_ingresos($pdo, [
                    'fecha' => $fecha,
                    'categoria' => 'Pago Cuenta Corriente',
                    'descripcion' => 'Cobro CC ' . $recibo,
                    'referencia' => $recibo,
                    'id_referencia' => $movId,
                    'usuario_id' => $usuarioId,
                    'observaciones' => $notas !== '' ? $notas : 'Recibo de cuenta corriente',
                ], $partesCaja);
            }

            if ($ownTx) {
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if ($ownTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return [
            'id' => $movId,
            'numero_recibo' => $recibo,
            'saldo' => cc_saldo($pdo, $clienteId),
        ];
    }
}

if (!function_exists('cc_aplicar_a_pedido')) {
    function cc_aplicar_a_pedido(PDO $pdo, int $clienteId, int $pedidoId, float $monto, ?int $usuarioId, string $notas = ''): array
    {
        $monto = round($monto, 2);
        if ($clienteId <= 0 || $pedidoId <= 0) {
            throw new Exception('Falta el cliente o el pedido.');
        }
        if ($monto <= 0) {
            throw new Exception('El monto a aplicar debe ser mayor a 0.');
        }

        $saldoCc = cc_saldo($pdo, $clienteId);
        if ($monto - $saldoCc > 0.009) {
            throw new Exception('El saldo a favor no alcanza. Disponible: ' . cc_fmt_money($saldoCc));
        }

        $stmt = $pdo->prepare("SELECT id, cliente_id, numero_pedido, total, estado FROM ecommerce_pedidos WHERE id = ? LIMIT 1");
        $stmt->execute([$pedidoId]);
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$pedido) {
            throw new Exception('Pedido no encontrado.');
        }
        if ((int)$pedido['cliente_id'] !== $clienteId) {
            throw new Exception('Ese pedido no es de este cliente.');
        }
        if (strtolower((string)$pedido['estado']) === 'cancelado') {
            throw new Exception('No se puede aplicar a un pedido cancelado.');
        }

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(monto), 0) FROM ecommerce_pedido_pagos WHERE pedido_id = ?");
        $stmt->execute([$pedidoId]);
        $pagado = round((float)$stmt->fetchColumn(), 2);
        $saldoPedido = round((float)$pedido['total'] - $pagado, 2);
        if ($monto - $saldoPedido > 0.009) {
            throw new Exception('El monto supera el saldo del pedido. Saldo: ' . cc_fmt_money($saldoPedido));
        }

        $ownTx = !$pdo->inTransaction();
        if ($ownTx) {
            $pdo->beginTransaction();
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO ecommerce_pedido_pagos (pedido_id, monto, metodo, referencia, notas, creado_por)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $pedidoId,
                $monto,
                'Cuenta corriente',
                'Aplicación CC',
                $notas !== '' ? $notas : 'Aplicado desde cuenta corriente',
                $usuarioId,
            ]);
            $pagoId = (int)$pdo->lastInsertId();

            $stmt = $pdo->prepare("
                INSERT INTO ecommerce_cuenta_corriente
                    (cliente_id, tipo, monto, metodo, referencia, notas, pedido_id, pago_pedido_id, fecha, creado_por)
                VALUES (?, 'aplicacion', ?, 'Cuenta corriente', ?, ?, ?, ?, NOW(), ?)
            ");
            $stmt->execute([
                $clienteId,
                $monto,
                (string)$pedido['numero_pedido'],
                $notas !== '' ? $notas : null,
                $pedidoId,
                $pagoId,
                $usuarioId,
            ]);

            $pagadoNuevo = round($pagado + $monto, 2);
            if ($pagadoNuevo >= round((float)$pedido['total'], 2) && strtolower((string)$pedido['estado']) === 'pendiente_pago') {
                try {
                    $pdo->prepare("UPDATE ecommerce_pedidos SET estado = 'pagado' WHERE id = ?")->execute([$pedidoId]);
                } catch (Throwable $e) {
                    // el pago ya quedó registrado
                }
            }

            if ($ownTx) {
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if ($ownTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return [
            'pago_id' => $pagoId,
            'pedido_id' => $pedidoId,
            'saldo' => cc_saldo($pdo, $clienteId),
        ];
    }
}

if (!function_exists('cc_revertir_aplicacion_por_pago')) {
    function cc_revertir_aplicacion_por_pago(PDO $pdo, int $pagoPedidoId): void
    {
        if ($pagoPedidoId <= 0) {
            return;
        }
        $stmt = $pdo->prepare("DELETE FROM ecommerce_cuenta_corriente WHERE tipo = 'aplicacion' AND pago_pedido_id = ?");
        $stmt->execute([$pagoPedidoId]);
    }
}

if (!function_exists('cc_pedidos_con_saldo')) {
    function cc_pedidos_con_saldo(PDO $pdo, int $clienteId): array
    {
        $stmt = $pdo->prepare("
            SELECT p.id, p.numero_pedido, p.total, p.estado, p.fecha_creacion,
                   COALESCE(pag.pagado, 0) AS pagado,
                   ROUND(p.total - COALESCE(pag.pagado, 0), 2) AS saldo
            FROM ecommerce_pedidos p
            LEFT JOIN (
                SELECT pedido_id, SUM(monto) AS pagado
                FROM ecommerce_pedido_pagos
                GROUP BY pedido_id
            ) pag ON pag.pedido_id = p.id
            WHERE p.cliente_id = ?
              AND COALESCE(p.estado, '') <> 'cancelado'
              AND (p.total - COALESCE(pag.pagado, 0)) > 0.009
            ORDER BY p.id DESC
        ");
        $stmt->execute([$clienteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('cc_listar_cuentas')) {
    function cc_listar_cuentas(PDO $pdo, string $buscar = ''): array
    {
        $params = [];
        $where = '1=1';
        if (trim($buscar) !== '') {
            $where = '(c.nombre LIKE ? OR c.email LIKE ? OR c.telefono LIKE ?)';
            $like = '%' . trim($buscar) . '%';
            $params = [$like, $like, $like];
        }

        $sql = "
            SELECT c.id, c.nombre, c.email, c.telefono,
                   COALESCE(SUM(CASE WHEN m.tipo = 'ingreso' THEN m.monto ELSE 0 END), 0) AS acreditado,
                   COALESCE(SUM(CASE WHEN m.tipo = 'aplicacion' THEN m.monto ELSE 0 END), 0) AS aplicado,
                   COALESCE(SUM(CASE WHEN m.tipo = 'ingreso' THEN m.monto WHEN m.tipo = 'aplicacion' THEN -m.monto ELSE 0 END), 0) AS saldo
            FROM ecommerce_clientes c
            LEFT JOIN ecommerce_cuenta_corriente m ON m.cliente_id = c.id
            WHERE {$where}
            GROUP BY c.id, c.nombre, c.email, c.telefono
        ";
        if (trim($buscar) === '') {
            $sql .= ' HAVING acreditado > 0 OR aplicado > 0';
        }
        $sql .= ' ORDER BY saldo DESC, c.nombre ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('cc_movimientos')) {
    function cc_movimientos(PDO $pdo, int $clienteId): array
    {
        $stmt = $pdo->prepare("
            SELECT m.*, p.numero_pedido
            FROM ecommerce_cuenta_corriente m
            LEFT JOIN ecommerce_pedidos p ON p.id = m.pedido_id
            WHERE m.cliente_id = ?
            ORDER BY m.fecha DESC, m.id DESC
        ");
        $stmt->execute([$clienteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('cc_empresa_membrete')) {
    /**
     * @return array{nombre:string,logo:?string,cuit:string,email:string,telefono:string,direccion:string}
     */
    function cc_empresa_membrete(PDO $pdo): array
    {
        $out = [
            'nombre' => 'Tucu Roller',
            'logo' => null,
            'cuit' => '',
            'email' => '',
            'telefono' => '',
            'direccion' => '',
        ];
        try {
            $row = $pdo->query('SELECT * FROM ecommerce_empresa LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: [];
            if (!empty($row['nombre'])) {
                $out['nombre'] = (string)$row['nombre'];
            }
            $out['cuit'] = trim((string)($row['cuit'] ?? ''));
            $out['email'] = trim((string)($row['email'] ?? ''));
            $out['telefono'] = trim((string)($row['telefono'] ?? ''));
            $dir = trim((string)($row['direccion'] ?? ''));
            $ciudad = trim((string)($row['ciudad'] ?? ''));
            $prov = trim((string)($row['provincia'] ?? ''));
            $out['direccion'] = trim($dir . (($ciudad !== '' || $prov !== '') ? ' · ' . trim($ciudad . ' ' . $prov) : ''));
            $archivo = trim((string)($row['logo'] ?? ''));
            if ($archivo !== '') {
                $ecommerce = dirname(__DIR__, 2);
                foreach ([$ecommerce . '/uploads/' . $archivo, dirname($ecommerce) . '/uploads/' . $archivo] as $path) {
                    if (is_file($path)) {
                        $out['logo'] = '/uploads/' . $archivo;
                        break;
                    }
                }
            }
        } catch (Throwable $e) {
            // nombre por defecto
        }
        return $out;
    }
}
