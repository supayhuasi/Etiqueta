<?php
require '../includes/header.php';

// Asegurar columnas de empleados (migración ligera)
$col = $pdo->query("SHOW COLUMNS FROM empleados LIKE 'documento'");
if ($col->rowCount() === 0) {
    $pdo->exec("ALTER TABLE empleados ADD COLUMN documento VARCHAR(20) AFTER email");
    $pdo->exec("ALTER TABLE empleados ADD COLUMN tipo_documento ENUM('DNI', 'CUIT', 'Pasaporte') DEFAULT 'DNI' AFTER documento");
    $pdo->exec("ALTER TABLE empleados ADD COLUMN telefono VARCHAR(20) AFTER tipo_documento");
    $pdo->exec("ALTER TABLE empleados ADD COLUMN direccion VARCHAR(255) AFTER telefono");
    $pdo->exec("ALTER TABLE empleados ADD COLUMN ciudad VARCHAR(100) AFTER direccion");
    $pdo->exec("ALTER TABLE empleados ADD COLUMN provincia VARCHAR(100) AFTER ciudad");
    $pdo->exec("ALTER TABLE empleados ADD COLUMN codigo_postal VARCHAR(10) AFTER provincia");
    $pdo->exec("ALTER TABLE empleados ADD COLUMN puesto VARCHAR(100) AFTER codigo_postal");
    $pdo->exec("ALTER TABLE empleados ADD COLUMN departamento VARCHAR(100) AFTER puesto");
    $pdo->exec("ALTER TABLE empleados ADD COLUMN fecha_ingreso DATE AFTER departamento");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO empleados (nombre, email, documento, tipo_documento, telefono, direccion, 
                                   ciudad, provincia, codigo_postal, puesto, departamento, 
                                   fecha_ingreso, sueldo_base, activo, fecha_creacion) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
        ");
        $stmt->execute([
            $_POST['nombre'],
            $_POST['email'],
            $_POST['documento'] ?? null,
            $_POST['tipo_documento'] ?? 'DNI',
            $_POST['telefono'] ?? null,
            $_POST['direccion'] ?? null,
            $_POST['ciudad'] ?? null,
            $_POST['provincia'] ?? null,
            $_POST['codigo_postal'] ?? null,
            $_POST['puesto'] ?? null,
            $_POST['departamento'] ?? null,
            $_POST['fecha_ingreso'] ?? null,
            floatval($_POST['sueldo_base'])
        ]);
        
        header("Location: sueldos.php?success=Empleado creado");
        exit;
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <h2>Crear Nuevo Empleado</h2>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <!-- Datos Personales -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h5>Datos Personales</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nombre Completo *</label>
                                <input type="text" class="form-control" name="nombre" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tipo de Documento</label>
                                <select class="form-select" name="tipo_documento">
                                    <option value="DNI">DNI</option>
                                    <option value="CUIT">CUIT</option>
                                    <option value="Pasaporte">Pasaporte</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Número de Documento</label>
                                <input type="text" class="form-control" name="documento" placeholder="Ej: 12345678">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Teléfono</label>
                                <input type="tel" class="form-control" name="telefono" placeholder="Ej: +54 9 3624 123456">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Datos de Contacto -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h5>Domicilio</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Dirección</label>
                            <input type="text" class="form-control" name="direccion" placeholder="Calle y número">
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Ciudad</label>
                                <input type="text" class="form-control" name="ciudad">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Provincia</label>
                                <input type="text" class="form-control" name="provincia">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">C.P.</label>
                                <input type="text" class="form-control" name="codigo_postal">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Datos Laborales -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h5>Información Laboral</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Puesto</label>
                                <input type="text" class="form-control" name="puesto" placeholder="Ej: Vendedor">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Departamento</label>
                                <input type="text" class="form-control" name="departamento" placeholder="Ej: Ventas">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Fecha de Ingreso</label>
                                <input type="date" class="form-control" name="fecha_ingreso">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Sueldo Base ($) *</label>
                                <input type="number" class="form-control" name="sueldo_base" step="0.01" required>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Guardar Empleado</button>
                    <a href="sueldos.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require '../includes/footer.php'; ?>
