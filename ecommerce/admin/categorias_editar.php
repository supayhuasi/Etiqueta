<?php
// Redirigir a crear con el ID para editar
$id = $_GET['id'] ?? 0;
header("Location: categorias_crear.php?id=$id");
exit;
