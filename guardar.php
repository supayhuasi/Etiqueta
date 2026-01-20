<?php
require 'config.php';

$data = [
    $_POST['tipo'],
    $_POST['numero_orden'],
    $_POST['ancho_cm'],
    $_POST['alto_cm'],
    $_POST['tela_id'] ?: null,
    $_POST['color_id'] ?: null
];

if (empty($_POST['id'])) {
    $sql = "INSERT INTO productos 
    (tipo, numero_orden, ancho_cm, alto_cm, tela_id, color_id)
    VALUES (?,?,?,?,?,?)";
    $pdo->prepare($sql)->execute($data);
} else {
    $data[] = $_POST['id'];
    $sql = "UPDATE productos SET
        tipo=?, numero_orden=?, ancho_cm=?, alto_cm=?, tela_id=?, color_id=?
        WHERE id=?";
    $pdo->prepare($sql)->execute($data);
}

header("Location: index.php");
