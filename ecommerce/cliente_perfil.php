<?php
require 'config.php';
require 'includes/header.php';
require 'includes/cliente_auth.php';

$cliente = require_cliente_login($pdo);

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $provincia = trim($_POST['provincia'] ?? '');
    $localidad = trim($_POST['localidad'] ?? '');
    $ciudad = trim($_POST['ciudad'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $codigo_postal = trim($_POST['codigo_postal'] ?? '');
    $responsabilidad_fiscal = trim($_POST['responsabilidad_fiscal'] ?? '');
    $documento_tipo = trim($_POST['documento_tipo'] ?? '');
    $documento_numero = trim($_POST['documento_numero'] ?? '');

    if ($nombre === '' || $localidad === '' || $provincia === '' || $responsabilidad_fiscal === '' || $documento_tipo === '' || $documento_numero === '') {
        $error = 'Completá los campos obligatorios.';
    } else {
        $stmt = $pdo->prepare("UPDATE ecommerce_clientes SET nombre = ?, telefono = ?, provincia = ?, localidad = ?, ciudad = ?, direccion = ?, codigo_postal = ?, responsabilidad_fiscal = ?, documento_tipo = ?, documento_numero = ? WHERE id = ?");
        $stmt->execute([
            $nombre,
            $telefono,
            $provincia,
            $localidad,
            $ciudad,
            $direccion,
            $codigo_postal,
            $responsabilidad_fiscal,
            $documento_tipo,
            $documento_numero,
            $cliente['id']
        ]);

        $_SESSION['cliente_nombre'] = $nombre;
        $stmt = $pdo->prepare("SELECT * FROM ecommerce_clientes WHERE id = ? LIMIT 1");
        $stmt->execute([$cliente['id']]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC) ?: $cliente;
        $mensaje = 'Datos actualizados correctamente.';
    }
}

$provincias = ['Buenos Aires','Catamarca','Chaco','Chubut','Córdoba','Corrientes','Entre Ríos','Formosa','Jujuy','La Pampa','La Rioja','Mendoza','Misiones','Neuquén','Río Negro','Salta','San Juan','San Luis','Santa Cruz','Santa Fe','Santiago del Estero','Tierra del Fuego','Tucumán'];
$responsabilidades = ['Consumidor Final','Monotributista','Responsable Inscripto','Exento','No Responsable','Sujeto No Categorizado'];
?>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1>Editar datos</h1>
            <p class="text-muted mb-0">Actualizá tu información de contacto y facturación.</p>
        </div>
        <a href="mis_pedidos.php" class="btn btn-secondary">Volver</a>
    </div>

    <?php if (!empty($mensaje)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="post">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nombre y apellido *</label>
                        <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($cliente['nombre'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" value="<?= htmlspecialchars($cliente['email'] ?? '') ?>" readonly>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Teléfono</label>
                        <input type="text" name="telefono" class="form-control" value="<?= htmlspecialchars($cliente['telefono'] ?? '') ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Código Postal</label>
                        <input type="text" name="codigo_postal" class="form-control" value="<?= htmlspecialchars($cliente['codigo_postal'] ?? '') ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Dirección</label>
                    <input type="text" name="direccion" class="form-control" value="<?= htmlspecialchars($cliente['direccion'] ?? '') ?>">
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Localidad *</label>
                        <input type="text" name="localidad" class="form-control" value="<?= htmlspecialchars($cliente['localidad'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Provincia *</label>
                        <select name="provincia" class="form-select" required>
                            <option value="">Seleccionar</option>
                            <?php foreach ($provincias as $prov): ?>
                                <option value="<?= htmlspecialchars($prov) ?>" <?= ($cliente['provincia'] ?? '') === $prov ? 'selected' : '' ?>><?= htmlspecialchars($prov) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Ciudad</label>
                    <input type="text" name="ciudad" class="form-control" value="<?= htmlspecialchars($cliente['ciudad'] ?? '') ?>">
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Responsabilidad Fiscal *</label>
                        <select class="form-select" name="responsabilidad_fiscal" required>
                            <option value="">Seleccionar</option>
                            <?php foreach ($responsabilidades as $rf): ?>
                                <option value="<?= htmlspecialchars($rf) ?>" <?= ($cliente['responsabilidad_fiscal'] ?? '') === $rf ? 'selected' : '' ?>><?= htmlspecialchars($rf) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Tipo Documento *</label>
                        <select class="form-select" name="documento_tipo" required>
                            <option value="">Seleccionar</option>
                            <option value="DNI" <?= ($cliente['documento_tipo'] ?? '') === 'DNI' ? 'selected' : '' ?>>DNI</option>
                            <option value="CUIT" <?= ($cliente['documento_tipo'] ?? '') === 'CUIT' ? 'selected' : '' ?>>CUIT</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Número *</label>
                        <input type="text" name="documento_numero" class="form-control" value="<?= htmlspecialchars($cliente['documento_numero'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
