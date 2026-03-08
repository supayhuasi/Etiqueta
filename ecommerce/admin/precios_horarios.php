<?php
require 'includes/header.php';

$mensaje = '';
$error = '';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS ecommerce_precio_horario_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        activo TINYINT(1) NOT NULL DEFAULT 0,
        tipo_ajuste ENUM('descuento','aumento') NOT NULL DEFAULT 'descuento',
        porcentaje DECIMAL(5,2) NOT NULL DEFAULT 5.00,
        hora_inicio TIME NOT NULL DEFAULT '22:00:00',
        hora_fin TIME NOT NULL DEFAULT '06:00:00',
        actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $stmt = $pdo->query("SELECT COUNT(*) FROM ecommerce_precio_horario_config");
    if ((int)$stmt->fetchColumn() === 0) {
        $pdo->exec("INSERT INTO ecommerce_precio_horario_config (activo, tipo_ajuste, porcentaje, hora_inicio, hora_fin) VALUES (0, 'descuento', 5.00, '22:00:00', '06:00:00')");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS ecommerce_precio_horario_categorias (
        id INT AUTO_INCREMENT PRIMARY KEY,
        categoria_id INT NOT NULL,
        UNIQUE KEY uniq_categoria (categoria_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {
    $error = 'No se pudo preparar la configuración de precios por horario: ' . $e->getMessage();
}

$categorias = [];
try {
    $stmt = $pdo->query("SELECT id, nombre FROM ecommerce_categorias ORDER BY nombre ASC");
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $categorias = [];
}

$categorias_configuradas = [];
try {
    $stmt = $pdo->query("SELECT categoria_id FROM ecommerce_precio_horario_categorias");
    $categorias_configuradas = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
} catch (Throwable $e) {
    $categorias_configuradas = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'guardar_config') {
    try {
        $activo = isset($_POST['activo']) ? 1 : 0;
        $tipo = trim($_POST['tipo_ajuste'] ?? 'descuento');
        $porcentaje = (float)($_POST['porcentaje'] ?? 0);
        $hora_inicio = trim($_POST['hora_inicio'] ?? '22:00');
        $hora_fin = trim($_POST['hora_fin'] ?? '06:00');
        $categorias_seleccionadas = $_POST['categorias'] ?? [];

        if (!is_array($categorias_seleccionadas)) {
            $categorias_seleccionadas = [];
        }

        $categorias_seleccionadas = array_values(array_unique(array_filter(array_map('intval', $categorias_seleccionadas), static fn($id) => $id > 0)));
        $categorias_validas = array_map(static fn($c) => (int)$c['id'], $categorias);
        $categorias_seleccionadas = array_values(array_filter(
            $categorias_seleccionadas,
            static fn($id) => in_array($id, $categorias_validas, true)
        ));

        if (!in_array($tipo, ['descuento', 'aumento'], true)) {
            throw new Exception('Tipo de ajuste inválido.');
        }

        if ($porcentaje < 0 || $porcentaje > 80) {
            throw new Exception('El porcentaje debe estar entre 0 y 80.');
        }

        if (!preg_match('/^([01]\\d|2[0-3]):([0-5]\\d)$/', $hora_inicio) || !preg_match('/^([01]\\d|2[0-3]):([0-5]\\d)$/', $hora_fin)) {
            throw new Exception('Las horas deben tener formato HH:MM.');
        }

        $hora_inicio_db = $hora_inicio . ':00';
        $hora_fin_db = $hora_fin . ':00';

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE ecommerce_precio_horario_config
            SET activo = ?, tipo_ajuste = ?, porcentaje = ?, hora_inicio = ?, hora_fin = ?
            WHERE id = (SELECT id2 FROM (SELECT id AS id2 FROM ecommerce_precio_horario_config ORDER BY id ASC LIMIT 1) t)");
        $stmt->execute([$activo, $tipo, round($porcentaje, 2), $hora_inicio_db, $hora_fin_db]);

        $pdo->exec("DELETE FROM ecommerce_precio_horario_categorias");
        if (!empty($categorias_seleccionadas)) {
            $stmt = $pdo->prepare("INSERT INTO ecommerce_precio_horario_categorias (categoria_id) VALUES (?)");
            foreach ($categorias_seleccionadas as $categoria_id) {
                $stmt->execute([$categoria_id]);
            }
        }

        $pdo->commit();
        $categorias_configuradas = $categorias_seleccionadas;

        $mensaje = 'Configuración guardada correctamente.';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

$config = [
    'activo' => 0,
    'tipo_ajuste' => 'descuento',
    'porcentaje' => 5.00,
    'hora_inicio' => '22:00:00',
    'hora_fin' => '06:00:00',
];

try {
    $stmt = $pdo->query("SELECT * FROM ecommerce_precio_horario_config ORDER BY id ASC LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $config = array_merge($config, $row);
    }
} catch (Throwable $e) {
}

$hora_actual = date('H:i:s');
$inicio = $config['hora_inicio'];
$fin = $config['hora_fin'];

$franja_activa = false;
if ($inicio <= $fin) {
    $franja_activa = ($hora_actual >= $inicio && $hora_actual <= $fin);
} else {
    $franja_activa = ($hora_actual >= $inicio || $hora_actual <= $fin);
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>Precios por Horario</h1>
        <p class="text-muted mb-0">Aplicá un ajuste automático de precios en una franja horaria (por ejemplo, descuento nocturno).</p>
    </div>
    <a href="precios_ecommerce.php" class="btn btn-outline-secondary">Precios Ecommerce</a>
</div>

<?php if ($mensaje !== ''): ?>
    <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-7">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">Configuración</div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <input type="hidden" name="action" value="guardar_config">

                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="activo" name="activo" <?= !empty($config['activo']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="activo"><strong>Activar ajuste horario</strong></label>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Tipo</label>
                        <select name="tipo_ajuste" class="form-select" required>
                            <option value="descuento" <?= ($config['tipo_ajuste'] ?? '') === 'descuento' ? 'selected' : '' ?>>Descuento</option>
                            <option value="aumento" <?= ($config['tipo_ajuste'] ?? '') === 'aumento' ? 'selected' : '' ?>>Aumento</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Porcentaje</label>
                        <input type="number" class="form-control" name="porcentaje" min="0" max="80" step="0.01" required value="<?= htmlspecialchars((string)$config['porcentaje']) ?>">
                    </div>

                    <div class="col-md-4 d-flex align-items-end">
                        <div class="alert alert-light border w-100 mb-0 text-center">
                            <strong><?= strtoupper((string)$config['tipo_ajuste']) ?></strong>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Desde</label>
                        <input type="time" class="form-control" name="hora_inicio" required value="<?= htmlspecialchars(substr((string)$config['hora_inicio'], 0, 5)) ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Hasta</label>
                        <input type="time" class="form-control" name="hora_fin" required value="<?= htmlspecialchars(substr((string)$config['hora_fin'], 0, 5)) ?>">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Categorías alcanzadas</label>
                        <div class="border rounded p-2" style="max-height: 220px; overflow-y: auto;">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="cat_all" disabled <?= empty($categorias_configuradas) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="cat_all">
                                    Todas las categorías (si no seleccionás ninguna abajo)
                                </label>
                            </div>
                            <?php if (empty($categorias)): ?>
                                <div class="text-muted small">No hay categorías disponibles.</div>
                            <?php else: ?>
                                <?php foreach ($categorias as $cat): ?>
                                    <?php $cat_id = (int)$cat['id']; ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="categorias[]" value="<?= $cat_id ?>" id="cat_<?= $cat_id ?>" <?= in_array($cat_id, $categorias_configuradas, true) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="cat_<?= $cat_id ?>">
                                            <?= htmlspecialchars($cat['nombre']) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <small class="text-muted">Si no tildás ninguna categoría, el ajuste se aplica a todos los productos.</small>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-success">Guardar configuración</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card mb-4">
            <div class="card-header">Estado actual</div>
            <div class="card-body">
                <div class="mb-2"><strong>Hora actual:</strong> <?= htmlspecialchars(date('d/m/Y H:i:s')) ?></div>
                <div class="mb-2"><strong>Franja configurada:</strong> <?= htmlspecialchars(substr((string)$config['hora_inicio'], 0, 5)) ?> → <?= htmlspecialchars(substr((string)$config['hora_fin'], 0, 5)) ?></div>
                <div class="mb-2"><strong>Ajuste:</strong> <?= htmlspecialchars(ucfirst((string)$config['tipo_ajuste'])) ?> <?= number_format((float)$config['porcentaje'], 2, ',', '.') ?>%</div>
                <div class="mb-2">
                    <strong>Categorías:</strong>
                    <?php if (empty($categorias_configuradas)): ?>
                        Todas
                    <?php else: ?>
                        <?= count($categorias_configuradas) ?> seleccionada(s)
                    <?php endif; ?>
                </div>
                <div>
                    <strong>Aplicando ahora:</strong>
                    <?php if (!empty($config['activo']) && $franja_activa): ?>
                        <span class="badge bg-success">Sí</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">No</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Cómo funciona</div>
            <div class="card-body small text-muted">
                <ul class="mb-0">
                    <li>Se aplica sobre el precio público ya calculado (lista por producto/categoría).</li>
                    <li>Podés limitar el ajuste a categorías específicas o dejarlo global.</li>
                    <li>Si la franja cruza medianoche (ej. 22:00 a 06:00), también funciona correctamente.</li>
                    <li>Afecta tienda, producto y API pública de precios.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
