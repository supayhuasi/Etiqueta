<?php
require 'includes/header.php';

$mensaje = '';
$error = '';
$link_generado = null;

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS ecommerce_mp_links (
        id INT AUTO_INCREMENT PRIMARY KEY,
        titulo VARCHAR(180) NOT NULL,
        descripcion VARCHAR(255) NULL,
        monto DECIMAL(12,2) NOT NULL,
        external_reference VARCHAR(120) NULL,
        fecha_vencimiento DATETIME NULL,
        preference_id VARCHAR(120) NULL,
        init_point TEXT NULL,
        sandbox_init_point TEXT NULL,
        creado_por INT NULL,
        creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_creado_en (creado_en),
        INDEX idx_external_reference (external_reference)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {
    $error = 'No se pudo preparar la tabla de links de pago: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generar_link') {
    try {
        $titulo = trim($_POST['titulo'] ?? 'Link de pago');
        $descripcion = trim($_POST['descripcion'] ?? 'Pago generado desde admin');
        $monto = (float)($_POST['monto'] ?? 0);
        $external_reference = trim($_POST['external_reference'] ?? '');
        $fecha_vencimiento = trim($_POST['fecha_vencimiento'] ?? '');

        if ($titulo === '') {
            $titulo = 'Link de pago';
        }
        if ($monto <= 0) {
            throw new Exception('El monto debe ser mayor a 0.');
        }

        $stmt = $pdo->query("SELECT * FROM ecommerce_mercadopago_config WHERE activo = 1 LIMIT 1");
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$config) {
            throw new Exception('Mercado Pago no está activo. Configuralo primero.');
        }

        $access_token = ($config['modo'] ?? 'test') === 'test'
            ? trim((string)($config['access_token_test'] ?? ''))
            : trim((string)($config['access_token_produccion'] ?? ''));

        if ($access_token === '') {
            throw new Exception('Falta Access Token de Mercado Pago para el modo activo.');
        }

        if ($external_reference === '') {
            $external_reference = 'LINK-' . date('YmdHis') . '-' . random_int(1000, 9999);
        }

        $date_of_expiration = null;
        if ($fecha_vencimiento !== '') {
            $dt = DateTime::createFromFormat('Y-m-d\\TH:i', $fecha_vencimiento);
            if (!$dt) {
                throw new Exception('La fecha de vencimiento no es válida.');
            }
            $date_of_expiration = $dt->format('Y-m-d\\TH:i:sP');
        }

        $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/ecommerce';

        $preference = [
            'items' => [[
                'title' => $titulo,
                'description' => $descripcion,
                'quantity' => 1,
                'currency_id' => 'ARS',
                'unit_price' => round($monto, 2),
            ]],
            'external_reference' => $external_reference,
            'back_urls' => [
                'success' => $base_url . '/mp_success.php',
                'failure' => $base_url . '/mp_failure.php',
                'pending' => $base_url . '/mp_pending.php',
            ],
            'auto_return' => 'approved',
            'notification_url' => (string)($config['notification_url'] ?? ''),
        ];

        if (!empty($config['descripcion_defecto'])) {
            $preference['statement_descriptor'] = mb_substr((string)$config['descripcion_defecto'], 0, 22);
        }

        if ($date_of_expiration !== null) {
            $preference['expires'] = true;
            $preference['date_of_expiration'] = $date_of_expiration;
        }

        $ch = curl_init('https://api.mercadopago.com/checkout/preferences');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $access_token,
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($preference));

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $curl_error) {
            throw new Exception('Error al conectar con Mercado Pago: ' . $curl_error);
        }

        $data = json_decode($response, true);
        if ($http_code !== 201 || !is_array($data) || empty($data['id'])) {
            $detalle = is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : (string)$response;
            throw new Exception('Mercado Pago rechazó la solicitud. Detalle: ' . $detalle);
        }

        $init_point = (string)($data['init_point'] ?? '');
        $sandbox_init_point = (string)($data['sandbox_init_point'] ?? '');

        $creado_por = null;
        if (isset($_SESSION['user']['id']) && is_numeric($_SESSION['user']['id'])) {
            $creado_por = (int)$_SESSION['user']['id'];
        }

        $stmt = $pdo->prepare("INSERT INTO ecommerce_mp_links
            (titulo, descripcion, monto, external_reference, fecha_vencimiento, preference_id, init_point, sandbox_init_point, creado_por)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $titulo,
            $descripcion !== '' ? $descripcion : null,
            round($monto, 2),
            $external_reference,
            $date_of_expiration !== null ? str_replace('T', ' ', substr($date_of_expiration, 0, 19)) : null,
            (string)$data['id'],
            $init_point !== '' ? $init_point : null,
            $sandbox_init_point !== '' ? $sandbox_init_point : null,
            $creado_por,
        ]);

        $link_generado = [
            'url' => $init_point !== '' ? $init_point : $sandbox_init_point,
            'id' => (string)$data['id'],
            'reference' => $external_reference,
        ];

        $mensaje = 'Link de pago generado correctamente.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$historial = [];
try {
    $stmt = $pdo->query("SELECT * FROM ecommerce_mp_links ORDER BY id DESC LIMIT 20");
    $historial = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>Link de Pago Mercado Pago</h1>
        <p class="text-muted mb-0">Generá un link directo con monto fijo para cobrar por WhatsApp, mail o redes.</p>
    </div>
    <a href="mp_config.php" class="btn btn-outline-secondary">Configurar Mercado Pago</a>
</div>

<?php if ($mensaje !== ''): ?>
    <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($link_generado && !empty($link_generado['url'])): ?>
    <div class="card border-success mb-4">
        <div class="card-header bg-success text-white">Link generado</div>
        <div class="card-body">
            <div class="mb-2"><strong>Preference ID:</strong> <?= htmlspecialchars($link_generado['id']) ?></div>
            <div class="mb-2"><strong>Referencia:</strong> <?= htmlspecialchars($link_generado['reference']) ?></div>
            <label class="form-label">URL de pago</label>
            <input type="text" class="form-control" readonly value="<?= htmlspecialchars($link_generado['url']) ?>" onclick="this.select();">
            <small class="text-muted d-block mt-2">Copiala y compartila con el cliente.</small>
        </div>
    </div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header bg-primary text-white">Nuevo link de pago</div>
    <div class="card-body">
        <form method="POST" class="row g-3">
            <input type="hidden" name="action" value="generar_link">

            <div class="col-md-6">
                <label class="form-label">Título *</label>
                <input type="text" name="titulo" class="form-control" maxlength="180" required value="Pago Tucu Roller">
            </div>

            <div class="col-md-3">
                <label class="form-label">Monto (ARS) *</label>
                <input type="number" name="monto" class="form-control" step="0.01" min="0.01" required>
            </div>

            <div class="col-md-3">
                <label class="form-label">Vence (opcional)</label>
                <input type="datetime-local" name="fecha_vencimiento" class="form-control">
            </div>

            <div class="col-md-6">
                <label class="form-label">Referencia externa</label>
                <input type="text" name="external_reference" class="form-control" maxlength="120" placeholder="Se genera automática si lo dejás vacío">
            </div>

            <div class="col-md-6">
                <label class="form-label">Descripción</label>
                <input type="text" name="descripcion" class="form-control" maxlength="255" value="Cobro generado desde administración">
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-success">Generar link</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">Últimos links generados</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Título</th>
                        <th>Monto</th>
                        <th>Referencia</th>
                        <th>URL</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($historial)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">Sin links generados todavía.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($historial as $fila): ?>
                            <?php $url = $fila['init_point'] ?: $fila['sandbox_init_point']; ?>
                            <tr>
                                <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($fila['creado_en']))) ?></td>
                                <td><?= htmlspecialchars($fila['titulo']) ?></td>
                                <td>$<?= number_format((float)$fila['monto'], 2, ',', '.') ?></td>
                                <td><?= htmlspecialchars($fila['external_reference'] ?? '-') ?></td>
                                <td>
                                    <?php if (!empty($url)): ?>
                                        <a href="<?= htmlspecialchars($url) ?>" target="_blank" class="btn btn-sm btn-outline-primary">Abrir</a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
