<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

unset($_SESSION['cliente_id'], $_SESSION['cliente_nombre']);

header('Location: index.php');
exit;
?>
