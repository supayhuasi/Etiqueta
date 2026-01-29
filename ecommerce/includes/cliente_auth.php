<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($pdo)) {
    require __DIR__ . '/../config.php';
}

function cliente_actual(PDO $pdo): ?array {
    if (empty($_SESSION['cliente_id'])) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT * FROM ecommerce_clientes WHERE id = ? AND activo = 1");
    $stmt->execute([$_SESSION['cliente_id']]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cliente) {
        unset($_SESSION['cliente_id'], $_SESSION['cliente_nombre']);
        return null;
    }

    return $cliente;
}

function require_cliente_login(PDO $pdo): array {
    $cliente = cliente_actual($pdo);
    if (!$cliente) {
        header('Location: cliente_login.php');
        exit;
    }

    return $cliente;
}
?>
