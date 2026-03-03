<?php
require 'config.php';
$nombre='%Ana Dominguez%';
$stmt=$pdo->prepare('SELECT ps.*, e.nombre AS empleado_nombre FROM pagos_sueldos ps JOIN empleados e ON ps.empleado_id=e.id WHERE e.nombre LIKE ?');
$stmt->execute([$nombre]);
$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($rows as $r){print_r($r);}
