<?php
session_start();
session_destroy();
// Redirigir al login del admin
header("Location: login.php");
