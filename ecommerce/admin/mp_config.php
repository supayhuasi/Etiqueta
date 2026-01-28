<?php
require 'includes/header.php';

$mensaje = '';
$error = '';

// Obtener configuración actual
$stmt = $pdo->query("SELECT * FROM ecommerce_mercadopago_config LIMIT 1");
$config = $stmt->fetch(PDO::FETCH_ASSOC) ?? [];

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $activo = isset($_POST['activo']) ? 1 : 0;
        $modo = $_POST['modo'] ?? 'test';
        $public_key_test = $_POST['public_key_test'] ?? '';
        $access_token_test = $_POST['access_token_test'] ?? '';
        $public_key_produccion = $_POST['public_key_produccion'] ?? '';
        $access_token_produccion = $_POST['access_token_produccion'] ?? '';
        $descripcion_defecto = $_POST['descripcion_defecto'] ?? 'Pago en tienda';
        
        // Validaciones
        if ($activo && empty($access_token_test) && empty($access_token_produccion)) {
            throw new Exception("Debes proporcionar al menos un Access Token");
        }
        
        if ($modo === 'test' && empty($access_token_test)) {
            throw new Exception("Falta Access Token para modo de prueba");
        }
        
        if ($modo === 'produccion' && empty($access_token_produccion)) {
            throw new Exception("Falta Access Token para modo de producción");
        }
        
        // Preparar URL de notificación
        $notification_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/ecommerce/admin/mp_webhook.php';
        
        if ($config) {
            // Actualizar configuración existente
            $stmt = $pdo->prepare("
                UPDATE ecommerce_mercadopago_config 
                SET activo = ?, modo = ?, public_key_test = ?, access_token_test = ?, 
                    public_key_produccion = ?, access_token_produccion = ?, 
                    notification_url = ?, descripcion_defecto = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $activo,
                $modo,
                $public_key_test,
                $access_token_test,
                $public_key_produccion,
                $access_token_produccion,
                $notification_url,
                $descripcion_defecto,
                $config['id']
            ]);
        } else {
            // Crear nueva configuración
            $stmt = $pdo->prepare("
                INSERT INTO ecommerce_mercadopago_config 
                (activo, modo, public_key_test, access_token_test, public_key_produccion, access_token_produccion, notification_url, descripcion_defecto)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $activo,
                $modo,
                $public_key_test,
                $access_token_test,
                $public_key_produccion,
                $access_token_produccion,
                $notification_url,
                $descripcion_defecto
            ]);
            $config = [
                'id' => $pdo->lastInsertId(),
                'activo' => $activo,
                'modo' => $modo
            ];
        }
        
        $mensaje = "✓ Configuración guardada correctamente";
        
        // Recargar configuración
        $stmt = $pdo->query("SELECT * FROM ecommerce_mercadopago_config LIMIT 1");
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>💳 Configuración Mercado Pago</h1>
        <p class="text-muted">Integra Mercado Pago para recibir pagos con tarjeta de crédito</p>
    </div>
    <a href="index.php" class="btn btn-secondary">← Volver</a>
</div>

<?php if ($mensaje): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= htmlspecialchars($mensaje) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Información -->
    <div class="col-md-4 mb-4">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">ℹ️ Instrucciones</h5>
            </div>
            <div class="card-body">
                <p><strong>Para habilitar pagos con Mercado Pago:</strong></p>
                <ol>
                    <li>Crear una cuenta en <a href="https://www.mercadopago.com.ar" target="_blank">Mercado Pago</a></li>
                    <li>Ir a Cuenta → Configuración → Seguridad</li>
                    <li>Generar un Access Token para:
                        <ul>
                            <li>Producción (dinero real)</li>
                            <li>Prueba (para testear)</li>
                        </ul>
                    </li>
                    <li>Copiar los tokens aquí</li>
                    <li>Elegir el modo (prueba o producción)</li>
                    <li>Activar la integración</li>
                </ol>
                <div class="alert alert-warning mt-3">
                    <strong>⚠️ Importante:</strong> Usa modo "Prueba" mientras testeas. Cambia a "Producción" cuando estés listo para cobrar dinero real.
                </div>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">✓ Estado Actual</h5>
            </div>
            <div class="card-body">
                <p>
                    <strong>Integración:</strong><br>
                    <span class="badge bg-<?= $config['activo'] ? 'success' : 'danger' ?>">
                        <?= $config['activo'] ? '🔓 Activa' : '🔒 Inactiva' ?>
                    </span>
                </p>
                <p class="mt-2">
                    <strong>Modo:</strong><br>
                    <span class="badge bg-<?= $config['modo'] === 'test' ? 'warning' : 'info' ?>">
                        <?= ucfirst($config['modo']) ?>
                    </span>
                </p>
            </div>
        </div>
    </div>

    <!-- Formulario de configuración -->
    <div class="col-md-8">
        <form method="POST" class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">⚙️ Parámetros de Configuración</h5>
            </div>
            <div class="card-body">

                <!-- Estado de activación -->
                <div class="alert alert-light border mb-4">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="activo" name="activo" <?= $config['activo'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="activo">
                            <strong>Activar Integración de Mercado Pago</strong>
                        </label>
                    </div>
                </div>

                <!-- Modo de operación -->
                <div class="mb-4 p-3 bg-light rounded">
                    <label class="form-label"><strong>Modo de Operación</strong></label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="modo" id="modo_test" value="test" <?= ($config['modo'] ?? 'test') === 'test' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="modo_test">
                            <strong>🧪 Modo Prueba</strong>
                            <small class="text-muted d-block">Usa credenciales de prueba. No procesa dinero real.</small>
                        </label>
                    </div>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="radio" name="modo" id="modo_prod" value="produccion" <?= ($config['modo'] ?? 'test') === 'produccion' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="modo_prod">
                            <strong>🚀 Modo Producción</strong>
                            <small class="text-muted d-block">Usa credenciales reales. Procesa dinero de verdad.</small>
                        </label>
                    </div>
                </div>

                <hr>

                <!-- Credenciales de Prueba -->
                <h6 class="mb-3">🧪 Credenciales de Prueba</h6>
                <div class="mb-3">
                    <label for="public_key_test" class="form-label">Public Key (Prueba) *</label>
                    <input type="text" class="form-control" id="public_key_test" name="public_key_test" value="<?= htmlspecialchars($config['public_key_test'] ?? '') ?>">
                    <small class="text-muted">Opcional - para pruebas en cliente</small>
                </div>
                <div class="mb-4">
                    <label for="access_token_test" class="form-label">Access Token (Prueba) *</label>
                    <input type="password" class="form-control" id="access_token_test" name="access_token_test" value="<?= htmlspecialchars($config['access_token_test'] ?? '') ?>" placeholder="TEST-...">
                    <small class="text-muted">Token para procesar pagos de prueba</small>
                </div>

                <hr>

                <!-- Credenciales de Producción -->
                <h6 class="mb-3">🚀 Credenciales de Producción</h6>
                <div class="mb-3">
                    <label for="public_key_produccion" class="form-label">Public Key (Producción) *</label>
                    <input type="text" class="form-control" id="public_key_produccion" name="public_key_produccion" value="<?= htmlspecialchars($config['public_key_produccion'] ?? '') ?>">
                    <small class="text-muted">Opcional - para pagos reales en cliente</small>
                </div>
                <div class="mb-4">
                    <label for="access_token_produccion" class="form-label">Access Token (Producción) *</label>
                    <input type="password" class="form-control" id="access_token_produccion" name="access_token_produccion" value="<?= htmlspecialchars($config['access_token_produccion'] ?? '') ?>" placeholder="APP_USR-...">
                    <small class="text-muted">Token para procesar pagos reales</small>
                </div>

                <hr>

                <!-- Configuración adicional -->
                <h6 class="mb-3">🎯 Configuración Adicional</h6>
                <div class="mb-4">
                    <label for="descripcion_defecto" class="form-label">Descripción de Pagos</label>
                    <input type="text" class="form-control" id="descripcion_defecto" name="descripcion_defecto" value="<?= htmlspecialchars($config['descripcion_defecto'] ?? 'Pago en tienda') ?>" maxlength="255">
                    <small class="text-muted">Texto que aparecerá en Mercado Pago</small>
                </div>

                <!-- URL de notificación -->
                <div class="alert alert-info">
                    <strong>URL de Notificación (Webhook):</strong><br>
                    <code><?= (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/ecommerce/admin/mp_webhook.php' ?></code><br>
                    <small class="text-muted">Configura esta URL en Mercado Pago para recibir notificaciones de pagos</small>
                </div>
            </div>

            <div class="card-footer bg-light">
                <button type="submit" class="btn btn-primary btn-lg">
                    💾 Guardar Configuración
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.form-check-label {
    cursor: pointer;
}

.form-check-input {
    cursor: pointer;
}

code {
    background-color: #f5f5f5;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 0.9rem;
}
</style>

<?php require 'includes/footer.php'; ?>
