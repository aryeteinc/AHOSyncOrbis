<?php
/**
 * Archivo de configuración para SyncOrbisPhp
 */

// Configuración de la base de datos
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_DATABASE', getenv('DB_DATABASE') ?: 'OrbisAHOPHP');
define('DB_USERNAME', getenv('DB_USERNAME') ?: 'root');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '');

// Configuración de la API
define('API_URL', getenv('API_URL') ?: 'https://api.example.com');
define('API_KEY', getenv('API_KEY') ?: '');

// Configuración de la aplicación
define('APP_DEBUG', getenv('APP_DEBUG') ?: false);
define('IMAGES_FOLDER', __DIR__ . '/public/images');

// Zona horaria
date_default_timezone_set('America/Bogota');

// Función para obtener variables de entorno con valor por defecto
function env($key, $default = null) {
    return getenv($key) ?: $_ENV[$key] ?? $default;
}
