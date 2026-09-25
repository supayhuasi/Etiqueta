<?php

// admin_table_exists/admin_column_exists normalmente ya están definidas por includes/header.php,
// pero algunos endpoints (ej. gastos_api.php) incluyen este helper sin pasar por header.php primero.
if (!function_exists('admin_table_exists')) {
    function admin_table_exists(PDO $pdo, string $table): bool
    {
        try {
            // SHOW TABLES LIKE ? no se puede preparar como statement nativo en este
            // servidor (PDO::ATTR_EMULATE_PREPARES está desactivado); information_schema sí soporta placeholders.
            $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
            $stmt->execute([$table]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('admin_column_exists')) {
    function admin_column_exists(PDO $pdo, string $table, string $column): bool
    {
        try {
            $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1");
            $stmt->execute([$table, $column]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('ensureCuentasSchema')) {
    function ensureCuentasSchema(PDO $pdo): void
    {
        // Cada paso corre en su propio try/catch: si uno falla, no debe impedir
        // que los siguientes (más importantes, como crear la cuenta por defecto) se ejecuten.
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS cuentas (
                id INT PRIMARY KEY AUTO_INCREMENT,
                nombre VARCHAR(150) NOT NULL,
                tipo VARCHAR(50) NOT NULL DEFAULT 'Operativa',
                descripcion TEXT NULL,
                activo TINYINT(1) NOT NULL DEFAULT 1,
                orden_visual INT NOT NULL DEFAULT 0,
                fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
                fecha_actualizacion DATETIME ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_nombre (nombre),
                INDEX idx_activo (activo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (Throwable $e) {
            error_log('cuentas_helper: no se pudo crear la tabla cuentas: ' . $e->getMessage());
        }

        try {
            if (admin_table_exists($pdo, 'flujo_caja') && !admin_column_exists($pdo, 'flujo_caja', 'cuenta_id')) {
                $pdo->exec("ALTER TABLE flujo_caja ADD COLUMN cuenta_id INT NULL");
            }
        } catch (Throwable $e) {
            error_log('cuentas_helper: no se pudo agregar flujo_caja.cuenta_id: ' . $e->getMessage());
        }

        try {
            if (admin_table_exists($pdo, 'flujo_caja') && admin_column_exists($pdo, 'flujo_caja', 'cuenta_id')) {
                $stmtIdx = $pdo->prepare("
                    SELECT COUNT(*) FROM information_schema.statistics
                    WHERE table_schema = DATABASE() AND table_name = 'flujo_caja' AND index_name = 'idx_cuenta_id'
                ");
                $stmtIdx->execute();
                if ((int)$stmtIdx->fetchColumn() === 0) {
                    $pdo->exec("ALTER TABLE flujo_caja ADD INDEX idx_cuenta_id (cuenta_id)");
                }
            }
        } catch (Throwable $e) {
            error_log('cuentas_helper: no se pudo agregar el índice idx_cuenta_id: ' . $e->getMessage());
        }

        try {
            if (admin_table_exists($pdo, 'gastos') && !admin_column_exists($pdo, 'gastos', 'cuenta_id')) {
                $pdo->exec("ALTER TABLE gastos ADD COLUMN cuenta_id INT NULL");
            }
        } catch (Throwable $e) {
            error_log('cuentas_helper: no se pudo agregar gastos.cuenta_id: ' . $e->getMessage());
        }

        $defaultId = null;
        try {
            // Cuenta por defecto para movimientos históricos / sin cuenta asignada
            $stmt = $pdo->prepare("SELECT id FROM cuentas WHERE nombre = 'Caja / Operativa' LIMIT 1");
            $stmt->execute();
            $defaultId = $stmt->fetchColumn();
            if (!$defaultId) {
                $pdo->prepare("
                    INSERT INTO cuentas (nombre, tipo, descripcion, activo)
                    VALUES ('Caja / Operativa', 'Operativa', 'Cuenta por defecto para movimientos históricos y sin cuenta asignada', 1)
                ")->execute();
                $defaultId = $pdo->lastInsertId();
            }
        } catch (Throwable $e) {
            error_log('cuentas_helper: no se pudo crear la cuenta por defecto: ' . $e->getMessage());
        }

        try {
            if ($defaultId && admin_table_exists($pdo, 'flujo_caja') && admin_column_exists($pdo, 'flujo_caja', 'cuenta_id')) {
                $stmt = $pdo->prepare("UPDATE flujo_caja SET cuenta_id = ? WHERE cuenta_id IS NULL");
                $stmt->execute([$defaultId]);
            }
        } catch (Throwable $e) {
            error_log('cuentas_helper: no se pudo backfillear flujo_caja.cuenta_id: ' . $e->getMessage());
        }

        // FK: se agrega solo después de garantizar que no hay cuenta_id huérfano (backfill de arriba)
        try {
            if (admin_table_exists($pdo, 'flujo_caja') && admin_column_exists($pdo, 'flujo_caja', 'cuenta_id')) {
                $stmtFk = $pdo->prepare("
                    SELECT COUNT(*) FROM information_schema.table_constraints
                    WHERE table_schema = DATABASE() AND table_name = 'flujo_caja' AND constraint_name = 'fk_flujo_caja_cuenta'
                ");
                $stmtFk->execute();
                if ((int)$stmtFk->fetchColumn() === 0) {
                    $pdo->exec("ALTER TABLE flujo_caja ADD CONSTRAINT fk_flujo_caja_cuenta FOREIGN KEY (cuenta_id) REFERENCES cuentas(id)");
                }
            }
        } catch (Throwable $e) {
            // No bloquear la carga de la página si la FK no se puede agregar todavía (p.ej. datos huérfanos residuales)
            error_log('cuentas_helper: no se pudo agregar la FK fk_flujo_caja_cuenta: ' . $e->getMessage());
        }

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS cuentas_reparto (
                cuenta_id INT NOT NULL,
                porcentaje DECIMAL(6,2) NOT NULL DEFAULT 0,
                PRIMARY KEY (cuenta_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (Throwable $e) {
            error_log('cuentas_helper: no se pudo crear cuentas_reparto: ' . $e->getMessage());
        }
    }
}

if (!function_exists('cuentas_get_default_id')) {
    function cuentas_get_default_id(PDO $pdo): int
    {
        try {
            $stmt = $pdo->prepare("SELECT id FROM cuentas WHERE nombre = 'Caja / Operativa' LIMIT 1");
            $stmt->execute();
            $id = $stmt->fetchColumn();
            if ($id) {
                return (int)$id;
            }
            ensureCuentasSchema($pdo);
            $stmt->execute();
            return (int)($stmt->fetchColumn() ?: 0);
        } catch (Throwable $e) {
            error_log('cuentas_helper: cuentas_get_default_id falló: ' . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('cuentas_listar')) {
    function cuentas_listar(PDO $pdo, bool $soloActivas = true): array
    {
        try {
            $sql = "SELECT * FROM cuentas";
            if ($soloActivas) {
                $sql .= " WHERE activo = 1";
            }
            $sql .= " ORDER BY orden_visual ASC, nombre ASC";
            return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('cuentas_helper: cuentas_listar falló: ' . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('cuentas_get')) {
    function cuentas_get(PDO $pdo, int $id): ?array
    {
        try {
            $stmt = $pdo->prepare("SELECT * FROM cuentas WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('cuentas_saldo_movimientos_sql')) {
    // Fórmula compartida: replica el criterio de flujo_caja.php donde 'Pago de Sueldo'
    // siempre se contabiliza como egreso, aunque el tipo guardado sea 'ingreso'.
    function cuentas_saldo_movimientos_sql(): string
    {
        return "SUM(
            CASE
                WHEN tipo = 'ingreso' AND categoria <> 'Pago de Sueldo' THEN monto
                WHEN tipo = 'egreso' OR categoria = 'Pago de Sueldo' THEN -monto
                ELSE 0
            END
        )";
    }
}

if (!function_exists('cuentas_saldo_inicial_mes')) {
    function cuentas_saldo_inicial_mes(PDO $pdo, int $cuentaId, string $mesYm): float
    {
        try {
            $primerDia = $mesYm . '-01';
            $sql = "SELECT COALESCE(" . cuentas_saldo_movimientos_sql() . ", 0)
                    FROM flujo_caja WHERE cuenta_id = ? AND fecha < ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$cuentaId, $primerDia]);
            return (float)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0.0;
        }
    }
}

if (!function_exists('cuentas_saldo_total')) {
    function cuentas_saldo_total(PDO $pdo, int $cuentaId): float
    {
        try {
            $sql = "SELECT COALESCE(" . cuentas_saldo_movimientos_sql() . ", 0)
                    FROM flujo_caja WHERE cuenta_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$cuentaId]);
            return (float)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0.0;
        }
    }
}

if (!function_exists('cuentas_saldo_periodo')) {
    function cuentas_saldo_periodo(PDO $pdo, int $cuentaId, string $fechaInicio, string $fechaFin): array
    {
        try {
            $stmt = $pdo->prepare("
                SELECT
                    COALESCE(SUM(CASE WHEN tipo = 'ingreso' AND categoria <> 'Pago de Sueldo' THEN monto ELSE 0 END), 0) AS ingresos,
                    COALESCE(SUM(CASE WHEN tipo = 'egreso' OR categoria = 'Pago de Sueldo' THEN monto ELSE 0 END), 0) AS egresos
                FROM flujo_caja
                WHERE cuenta_id = ? AND fecha BETWEEN ? AND ?
            ");
            $stmt->execute([$cuentaId, $fechaInicio, $fechaFin]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['ingresos' => 0, 'egresos' => 0];
            $ingresos = (float)$row['ingresos'];
            $egresos = (float)$row['egresos'];
            return [
                'ingresos' => $ingresos,
                'egresos' => $egresos,
                'saldo' => $ingresos - $egresos,
            ];
        } catch (Throwable $e) {
            return ['ingresos' => 0.0, 'egresos' => 0.0, 'saldo' => 0.0];
        }
    }
}

if (!function_exists('cuentas_reparto_listar')) {
    /**
     * @return array<int,float> cuenta_id => porcentaje
     */
    function cuentas_reparto_listar(PDO $pdo): array
    {
        try {
            if (!admin_table_exists($pdo, 'cuentas_reparto')) {
                return [];
            }
            $rows = $pdo->query("SELECT cuenta_id, porcentaje FROM cuentas_reparto WHERE porcentaje > 0")->fetchAll(PDO::FETCH_ASSOC);
            $map = [];
            foreach ($rows as $row) {
                $map[(int)$row['cuenta_id']] = (float)$row['porcentaje'];
            }
            return $map;
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('cuentas_reparto_guardar')) {
    function cuentas_reparto_guardar(PDO $pdo, array $porcentajes): void
    {
        $limpios = [];
        foreach ($porcentajes as $cuentaId => $pct) {
            $cuentaId = (int)$cuentaId;
            $pct = round((float)$pct, 2);
            if ($cuentaId > 0 && $pct > 0) {
                $limpios[$cuentaId] = $pct;
            }
        }
        $suma = round(array_sum($limpios), 2);
        if (empty($limpios)) {
            throw new Exception('Indicá al menos una caja con porcentaje.');
        }
        if (abs($suma - 100) > 0.05) {
            throw new Exception('Los porcentajes tienen que sumar 100%. Ahora suman ' . number_format($suma, 2, ',', '.') . '%.');
        }

        $pdo->beginTransaction();
        try {
            $pdo->exec("DELETE FROM cuentas_reparto");
            $stmt = $pdo->prepare("INSERT INTO cuentas_reparto (cuenta_id, porcentaje) VALUES (?, ?)");
            foreach ($limpios as $cuentaId => $pct) {
                $stmt->execute([$cuentaId, $pct]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}

if (!function_exists('cuentas_reparto_desde_post')) {
    /**
     * @return list<array{cuenta_id:int,porcentaje:float}>
     */
    function cuentas_reparto_desde_post(array $post): array
    {
        $modo = (string)($post['reparto_modo'] ?? 'una');
        if ($modo !== 'varias') {
            $cuentaId = (int)($post['cuenta_id'] ?? 0);
            if ($cuentaId <= 0) {
                throw new Exception('Elegí la caja donde entra el pago.');
            }
            return [['cuenta_id' => $cuentaId, 'porcentaje' => 100.0]];
        }

        $ids = $post['reparto_cuenta'] ?? [];
        $pcts = $post['reparto_porcentaje'] ?? [];
        if (!is_array($ids) || !is_array($pcts)) {
            throw new Exception('El reparto de cajas no es válido.');
        }

        $splits = [];
        $vistos = [];
        foreach ($ids as $i => $idRaw) {
            $cuentaId = (int)$idRaw;
            $pct = round((float)($pcts[$i] ?? 0), 2);
            if ($cuentaId <= 0 || $pct <= 0) {
                continue;
            }
            if (isset($vistos[$cuentaId])) {
                throw new Exception('No repetís la misma caja en el reparto.');
            }
            $vistos[$cuentaId] = true;
            $splits[] = ['cuenta_id' => $cuentaId, 'porcentaje' => $pct];
        }
        if (empty($splits)) {
            throw new Exception('Indicá al menos una caja con porcentaje para repartir el pago.');
        }
        $suma = round(array_sum(array_column($splits, 'porcentaje')), 2);
        if (abs($suma - 100) > 0.05) {
            throw new Exception('Los porcentajes del pago tienen que sumar 100%. Ahora suman ' . number_format($suma, 2, ',', '.') . '%.');
        }
        return $splits;
    }
}

if (!function_exists('cuentas_reparto_partir_monto')) {
    /**
     * @param list<array{cuenta_id:int,porcentaje:float}> $splits
     * @return list<array{cuenta_id:int,porcentaje:float,monto:float}>
     */
    function cuentas_reparto_partir_monto(float $monto, array $splits): array
    {
        $monto = round($monto, 2);
        if ($monto <= 0) {
            throw new Exception('El monto debe ser mayor a 0');
        }
        $out = [];
        $acum = 0.0;
        $last = count($splits) - 1;
        foreach ($splits as $i => $split) {
            if ($i === $last) {
                $parte = round($monto - $acum, 2);
            } else {
                $parte = round($monto * ((float)$split['porcentaje']) / 100, 2);
                $acum = round($acum + $parte, 2);
            }
            if ($parte <= 0) {
                throw new Exception('Alguna caja quedó con $0. Revisá los porcentajes.');
            }
            $out[] = [
                'cuenta_id' => (int)$split['cuenta_id'],
                'porcentaje' => (float)$split['porcentaje'],
                'monto' => $parte,
            ];
        }
        return $out;
    }
}

if (!function_exists('cuentas_reparto_insertar_ingresos')) {
    /**
     * @param list<array{cuenta_id:int,porcentaje:float,monto:float}> $partes
     */
    function cuentas_reparto_insertar_ingresos(PDO $pdo, array $base, array $partes): int
    {
        $stmt = $pdo->prepare("
            INSERT INTO flujo_caja
            (fecha, tipo, categoria, descripcion, monto, referencia, id_referencia, cuenta_id, usuario_id, observaciones)
            VALUES (?, 'ingreso', ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($partes as $parte) {
            $obs = trim((string)($base['observaciones'] ?? ''));
            if (count($partes) > 1) {
                $extra = 'Reparto ' . number_format((float)$parte['porcentaje'], 2, ',', '.') . '%';
                $obs = $obs !== '' ? $obs . ' | ' . $extra : $extra;
            }
            $stmt->execute([
                $base['fecha'] ?? date('Y-m-d'),
                $base['categoria'] ?? 'Pago Pedido',
                $base['descripcion'] ?? '',
                $parte['monto'],
                $base['referencia'] ?? null,
                !empty($base['id_referencia']) ? (int)$base['id_referencia'] : null,
                (int)$parte['cuenta_id'],
                $base['usuario_id'] ?? null,
                $obs !== '' ? $obs : null,
            ]);
        }
        return count($partes);
    }
}

if (!function_exists('cuentas_reparto_render_campos')) {
    function cuentas_reparto_render_campos(array $cuentas, array $repartoDefault, array $opts = []): void
    {
        $required = !empty($opts['required']);
        $montoSelector = (string)($opts['monto_selector'] ?? '#monto, input[name="monto"]');
        $defaultsJson = [];
        foreach ($cuentas as $c) {
            $id = (int)$c['id'];
            if (isset($repartoDefault[$id]) && (float)$repartoDefault[$id] > 0) {
                $defaultsJson[] = ['id' => $id, 'pct' => (float)$repartoDefault[$id]];
            }
        }
        $tieneDefault = !empty($defaultsJson);
        ?>
        <div class="cuentas-reparto-box border rounded p-3 mb-3">
            <label class="form-label fw-semibold mb-2">Destino del pago</label>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="reparto_modo" id="repartoModoUna" value="una" checked>
                <label class="form-check-label" for="repartoModoUna">Una sola caja</label>
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="radio" name="reparto_modo" id="repartoModoVarias" value="varias">
                <label class="form-check-label" for="repartoModoVarias">Repartir en varias cajas por %</label>
            </div>

            <div data-reparto-una>
                <label class="form-label" for="cuenta_id">Caja</label>
                <select class="form-select" id="cuenta_id" name="cuenta_id" <?= $required ? 'required' : '' ?>>
                    <option value="">Seleccionar...</option>
                    <?php foreach ($cuentas as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars((string)$c['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div data-reparto-varias class="d-none">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <small class="text-muted">Los porcentajes tienen que sumar 100%.</small>
                    <?php if ($tieneDefault): ?>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-reparto-aplicar-default>Usar % configurados</button>
                    <?php endif; ?>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-2">
                        <thead>
                            <tr>
                                <th>Caja</th>
                                <th style="width: 120px;">%</th>
                                <th style="width: 140px;">Monto</th>
                                <th style="width: 50px;"></th>
                            </tr>
                        </thead>
                        <tbody data-reparto-filas></tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-reparto-agregar>+ Caja</button>
                    <strong>Total: <span data-reparto-total-pct>0</span>% · $<span data-reparto-total-monto>0,00</span></strong>
                </div>
            </div>
        </div>
        <script>
        (function () {
            const box = document.querySelector('.cuentas-reparto-box');
            if (!box || box.dataset.bound) return;
            box.dataset.bound = '1';
            const cuentas = <?= json_encode(array_map(static function ($c) {
                return ['id' => (int)$c['id'], 'nombre' => (string)$c['nombre']];
            }, $cuentas), JSON_UNESCAPED_UNICODE) ?>;
            const defaults = <?= json_encode($defaultsJson) ?>;
            const montoSelector = <?= json_encode($montoSelector) ?>;
            const una = box.querySelector('[data-reparto-una]');
            const varias = box.querySelector('[data-reparto-varias]');
            const filas = box.querySelector('[data-reparto-filas]');
            const selUna = box.querySelector('#cuenta_id');

            function montoPago() {
                const el = document.querySelector(montoSelector);
                return el ? (parseFloat(String(el.value).replace(',', '.')) || 0) : 0;
            }
            function fmt(n) {
                return (Math.round(n * 100) / 100).toFixed(2).replace('.', ',');
            }
            function optionsHtml(selected) {
                return '<option value="">Seleccionar...</option>' + cuentas.map(function (c) {
                    return '<option value="' + c.id + '"' + (String(c.id) === String(selected) ? ' selected' : '') + '>' + c.nombre + '</option>';
                }).join('');
            }
            function addFila(cuentaId, pct) {
                const tr = document.createElement('tr');
                tr.innerHTML = '<td><select class="form-select form-select-sm" name="reparto_cuenta[]">' + optionsHtml(cuentaId || '') + '</select></td>'
                    + '<td><input type="number" class="form-control form-control-sm" name="reparto_porcentaje[]" step="0.01" min="0" max="100" value="' + (pct || '') + '"></td>'
                    + '<td><span class="reparto-monto-fila">$0,00</span></td>'
                    + '<td><button type="button" class="btn btn-sm btn-outline-danger" data-reparto-quitar>&times;</button></td>';
                filas.appendChild(tr);
                bindFila(tr);
                recalc();
            }
            function bindFila(tr) {
                tr.querySelectorAll('input, select').forEach(function (el) {
                    el.addEventListener('input', recalc);
                    el.addEventListener('change', recalc);
                });
                const q = tr.querySelector('[data-reparto-quitar]');
                if (q) q.addEventListener('click', function () { tr.remove(); recalc(); });
            }
            function recalc() {
                const totalMonto = montoPago();
                let sumaPct = 0;
                let sumaMonto = 0;
                const rows = Array.prototype.slice.call(filas.querySelectorAll('tr'));
                rows.forEach(function (tr, i) {
                    const pct = parseFloat(tr.querySelector('input[name="reparto_porcentaje[]"]').value) || 0;
                    sumaPct += pct;
                    let parte = Math.round(totalMonto * pct) / 100;
                    if (i === rows.length - 1 && rows.length > 0 && Math.abs(sumaPct - 100) < 0.06) {
                        parte = Math.round((totalMonto - sumaMonto) * 100) / 100;
                    } else {
                        sumaMonto = Math.round((sumaMonto + parte) * 100) / 100;
                    }
                    tr.querySelector('.reparto-monto-fila').textContent = '$' + fmt(parte);
                });
                box.querySelector('[data-reparto-total-pct]').textContent = (Math.round(sumaPct * 100) / 100).toString().replace('.', ',');
                box.querySelector('[data-reparto-total-monto]').textContent = fmt(rows.length ? (Math.abs(sumaPct - 100) < 0.06 ? totalMonto : (totalMonto * sumaPct / 100)) : 0);
            }
            function setModo(modo) {
                const esVarias = modo === 'varias';
                una.classList.toggle('d-none', esVarias);
                varias.classList.toggle('d-none', !esVarias);
                if (selUna) selUna.required = !esVarias;
                if (esVarias && !filas.children.length) {
                    if (defaults.length) {
                        defaults.forEach(function (d) { addFila(d.id, d.pct); });
                    } else {
                        addFila('', 100);
                    }
                }
                recalc();
            }
            box.querySelectorAll('input[name="reparto_modo"]').forEach(function (r) {
                r.addEventListener('change', function () { setModo(r.value); });
            });
            const btnDefault = box.querySelector('[data-reparto-aplicar-default]');
            if (btnDefault) {
                btnDefault.addEventListener('click', function () {
                    filas.innerHTML = '';
                    defaults.forEach(function (d) { addFila(d.id, d.pct); });
                });
            }
            const btnAdd = box.querySelector('[data-reparto-agregar]');
            if (btnAdd) btnAdd.addEventListener('click', function () { addFila('', ''); });
            const montoEl = document.querySelector(montoSelector);
            if (montoEl) {
                montoEl.addEventListener('input', recalc);
                montoEl.addEventListener('change', recalc);
            }
            setModo(box.querySelector('input[name="reparto_modo"]:checked').value);
        })();
        </script>
        <?php
    }
}
