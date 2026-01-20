<?php
require 'config.php';

$id = $_GET['id'];
$pdo->prepare("DELETE FROM productos WHERE id=?")->execute([$id]);

header("Location: index.php");
