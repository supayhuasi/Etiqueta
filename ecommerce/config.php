<?php
// Incluir la configuración principal
require '../config.php';

// Variable para controlar si estamos en ecommerce
define('ECOMMERCE_MODE', true);

// Configuración de OAuth con Google (usar variables de entorno)
$google_oauth = [
	'client_id' => getenv('GOOGLE_CLIENT_ID') ?: '',
	'client_secret' => getenv('GOOGLE_CLIENT_SECRET') ?: '',
	'redirect_uri' => getenv('GOOGLE_REDIRECT_URI') ?: ''
];
?>
