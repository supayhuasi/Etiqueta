<?php
// Test de navegación - verifica que $admin_url esté definido correctamente

require 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h2>Test de Navegación</h2>
            
            <div class="alert alert-info">
                <p><strong>$admin_url:</strong> <?= htmlspecialchars($admin_url) ?></p>
                <p><strong>$_SERVER['PHP_SELF']:</strong> <?= htmlspecialchars($_SERVER['PHP_SELF']) ?></p>
                <p><strong>Current File:</strong> <?= basename($_SERVER['PHP_SELF']) ?></p>
            </div>

            <h3>Enlaces de Prueba:</h3>
            <ul>
                <li><a href="<?= $admin_url ?>sueldos/sueldos.php">Ir a Sueldos</a></li>
                <li><a href="<?= $admin_url ?>asistencias/asistencias.php">Ir a Asistencias</a></li>
                <li><a href="<?= $admin_url ?>cheques/cheques.php">Ir a Cheques</a></li>
                <li><a href="<?= $admin_url ?>gastos/gastos.php">Ir a Gastos</a></li>
                <li><a href="<?= $admin_url ?>dashboard.php">Ir a Dashboard</a></li>
            </ul>

            <div class="alert alert-warning mt-3">
                <p>Haz clic en cualquier enlace y verifica que la URL en la barra de direcciones sea correcta.</p>
                <p>Ejemplo: debe ir a <code>/ecommerce/admin/sueldos/sueldos.php</code>, no a <code>/ecommerce/admin/test_navigation/sueldos/sueldos.php</code></p>
            </div>
        </div>
    </div>
</div>

</body>
</html>
