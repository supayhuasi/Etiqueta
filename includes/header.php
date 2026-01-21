<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Sistema Tucu Roller</title>
<link href="assets/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-dark bg-dark px-3">
  <span class="navbar-brand">Tucu Roller</span>
  <div class="text-white">
    <?= $_SESSION['user']['nombre'] ?>
    <a href="auth/logout.php" class="btn btn-sm btn-outline-light ms-2">Salir</a>
  </div>
</nav>

<div class="container mt-4">
