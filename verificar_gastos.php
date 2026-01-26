<?php
require 'config.php';

session_start();
if (!isset($_SESSION['user']) || $_SESSION['rol'] !== 'admin') {
    die("Acceso denegado");
}

$verificaciones = [];
$errores = 0;
$advertencias = 0;
$ok = 0;

// Verificar archivos
$archivos_requeridos = [
    'gastos.php',
    'gastos_crear.php',
    'gastos_editar.php',
    'gastos_eliminar.php',
    'gastos_cambiar_estado.php',
    'tipos_gastos.php',
    'setup_gastos.php',
    'verificar_gastos.php',
    'componentes/resumen_gastos.php'
];

foreach ($archivos_requeridos as $archivo) {
    if (file_exists($archivo)) {
        $verificaciones[] = ['✓ Archivo existente', $archivo, 'success'];
        $ok++;
    } else {
        $verificaciones[] = ['✗ Archivo faltante', $archivo, 'danger'];
        $errores++;
    }
}

// Verificar carpeta de uploads
if (is_dir('uploads/gastos')) {
    $verificaciones[] = ['✓ Carpeta uploads/gastos existe', '', 'success'];
    $ok++;
} else {
    $verificaciones[] = ['⚠ Carpeta uploads/gastos no existe', 'Crear manualmente', 'warning'];
    $advertencias++;
}

// Verificar permisos de escritura
if (is_writable('uploads/gastos')) {
    $verificaciones[] = ['✓ Permisos de escritura OK', 'uploads/gastos', 'success'];
    $ok++;
} else {
    $verificaciones[] = ['✗ Sin permisos de escritura', 'uploads/gastos', 'danger'];
    $errores++;
}

// Verificar tablas en BD
$tablas = ['gastos', 'tipos_gastos', 'estados_gastos', 'historial_gastos'];
foreach ($tablas as $tabla) {
    $stmt = $pdo->query("SHOW TABLES LIKE '$tabla'");
    if ($stmt->rowCount() > 0) {
        $verificaciones[] = ['✓ Tabla existente', "gastos.$tabla", 'success'];
        $ok++;
    } else {
        $verificaciones[] = ['✗ Tabla no existe', $tabla, 'danger'];
        $errores++;
    }
}

// Contar registros
if ($errores === 0) {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM tipos_gastos WHERE activo = 1");
    $tipos_count = $stmt->fetch()['total'];
    if ($tipos_count >= 5) {
        $verificaciones[] = ['✓ Tipos de gastos', "$tipos_count registros", 'success'];
        $ok++;
    } else {
        $verificaciones[] = ['⚠ Pocos tipos de gastos', "$tipos_count registros (recomendado >= 5)", 'warning'];
        $advertencias++;
    }

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM estados_gastos WHERE activo = 1");
    $estados_count = $stmt->fetch()['total'];
    if ($estados_count >= 5) {
        $verificaciones[] = ['✓ Estados de gastos', "$estados_count registros", 'success'];
        $ok++;
    } else {
        $verificaciones[] = ['⚠ Pocos estados de gastos', "$estados_count registros (recomendado >= 5)", 'warning'];
        $advertencias++;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Verificación - Módulo de Gastos</title>
    <link href="assets/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h1>🔍 Verificación del Módulo de Gastos</h1>
    
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h5>✓ OK</h5>
                    <h3><?= $ok ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body text-center">
                    <h5>⚠ Advertencias</h5>
                    <h3><?= $advertencias ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body text-center">
                    <h5>✗ Errores</h5>
                    <h3><?= $errores ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h5>Estado</h5>
                    <h3><?= $errores === 0 ? '✓' : '✗' ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5>Resultados</h5>
        </div>
        <div class="card-body">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Verificación</th>
                        <th>Detalle</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($verificaciones as $v): ?>
                    <tr class="table-<?= $v[2] ?>">
                        <td><?= $v[0] ?></td>
                        <td><?= $v[1] ?></td>
                        <td><span class="badge bg-<?= $v[2] ?>"><?= ucfirst($v[2]) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        <a href="index.php" class="btn btn-secondary">Volver</a>
    </div>
</div>
</body>
</html>
