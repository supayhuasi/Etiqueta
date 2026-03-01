<?php
// Evitar recursión infinita al incluir config.php
if (!defined('CONFIG_LOADED')) {
	require_once dirname(__DIR__) . '/config.php';
}

// Variable para controlar si estamos en ecommerce
if (!defined('ECOMMERCE_MODE')) {
	define('ECOMMERCE_MODE', true);
}

// Configuración de OAuth con Google (usar variables de entorno)
$google_oauth = [
	'client_id' => getenv('GOOGLE_CLIENT_ID') ?: '',
	'client_secret' => getenv('GOOGLE_CLIENT_SECRET') ?: '',
	'redirect_uri' => getenv('GOOGLE_REDIRECT_URI') ?: ''
];
?>
