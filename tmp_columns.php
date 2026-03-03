<?php
require 'config.php';
$cols = $pdo->query('SHOW COLUMNS FROM ecommerce_pedidos')->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    echo $c['Field'] . "\n";
}
