<?php
require 'includes/header.php';
require_once __DIR__ . '/includes/simulacion_helper.php';

if (!$can_access('dashboard_principal')) {
    die('Acceso denegado.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

admin_require_csrf_post();
ensureSimulacionSchema($pdo);

$descripcion = trim($_POST['descripcion'] ?? '');
$monto = (float)($_POST['monto'] ?? 0);
$fecha = trim($_POST['fecha'] ?? '');
$recurrente = !empty($_POST['recurrente_mensual']) ? 1 : 0;
$recurrente_hasta = trim($_POST['recurrente_hasta'] ?? '') ?: null;

$errores_form = [];
if ($descripcion === '') {
    $errores_form[] = 'La descripción es obligatoria';
}
if ($monto <= 0) {
    $errores_form[] = 'El monto debe ser mayor a 0';
}

$fecha_dt = DateTime::createFromFormat('Y-m-d', $fecha);
if (!$fecha_dt) {
    $errores_form[] = 'La fecha no es válida';
} elseif ($fecha_dt < new DateTime('today')) {
    $errores_form[] = 'La fecha debe ser hoy o futura';
}

if ($recurrente_hasta !== null && !DateTime::createFromFormat('Y-m-d', $recurrente_hasta)) {
    $recurrente_hasta = null;
}

if (empty($errores_form)) {
    $stmt = $pdo->prepare("INSERT INTO dashboard_gastos_simulados (descripcion, monto, fecha, recurrente_mensual, recurrente_hasta, usuario_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$descripcion, $monto, $fecha, $recurrente, $recurrente_hasta, $_SESSION['user']['id'] ?? null]);
    header('Location: dashboard.php?sim_ok=1');
    exit;
}

header('Location: dashboard.php?sim_error=1');
exit;
