<?php
// REDIRECT PARA COMPATIBILIDAD - El auth se movió a /ecommerce/admin/auth/
// Redirigir al nuevo logout
header("Location: ../ecommerce/admin/auth/logout.php");
exit;

