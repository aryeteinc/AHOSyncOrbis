<?php
/**
 * Configuración principal para SyncOrbisPhp
 * 
 * Este archivo contiene todas las configuraciones necesarias para el funcionamiento
 * del sistema de sincronización.
 */

// Cargar variables de entorno desde .env
$envFile = __DIR__ . '/.env';
if (!file_exists($envFile)) {
    // Intentar con la ruta alternativa
    $envFile = __DIR__ . '/../.env';
}
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $value = trim($parts[1]);
            // Eliminar comillas si existen
            if (strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) {
                $value = substr($value, 1, -1);
            }
            putenv("$key=$value");
            $_ENV[$key] = $value; // También establecer en $_ENV para mayor compatibilidad
        }
    }
}

// Configuración de la base de datos
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_DATABASE') ?: 'inmuebles');
define('DB_USER', getenv('DB_USERNAME') ?: 'root');
define('DB_PASS', getenv('DB_PASSWORD') ?: '');

// Aliases para compatibilidad con diferentes partes del código
define('DB_DATABASE', getenv('DB_DATABASE') ?: 'inmuebles');
define('DB_USERNAME', getenv('DB_USERNAME') ?: 'root');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '');

// Configuración de la API
define('API_URL', getenv('API_URL') ?: 'https://ahoinmobiliaria.webdgi.site/api/inmueble/restful/list/0c353a42-0bf1-432e-a7f8-6f87bab5f5fe/');
define('API_KEY', getenv('API_KEY') ?: '');

// Configuración de imágenes
define('IMAGES_STORAGE_MODE', getenv('IMAGES_STORAGE_MODE') ?: 'local');
define('IMAGES_BASE_PATH', getenv('IMAGES_FOLDER') ?: __DIR__ . '/../public/images/inmuebles');
define('LARAVEL_STORAGE_PATH', getenv('LARAVEL_STORAGE_PATH') ?: '');
define('LARAVEL_DISK', getenv('LARAVEL_DISK') ?: 'public');
define('LARAVEL_IMAGES_PATH', getenv('LARAVEL_IMAGES_PATH') ?: 'images/inmuebles');

// Configurar la ruta de imágenes según el modo de almacenamiento
if (IMAGES_STORAGE_MODE === 'laravel' && !empty(LARAVEL_STORAGE_PATH)) {
    // Modo Laravel: usar la ruta de storage de Laravel
    define('IMAGES_FOLDER', rtrim(LARAVEL_STORAGE_PATH, '/') . '/' . LARAVEL_IMAGES_PATH);
    define('IMAGES_DISK_MODE', 'laravel');
} else {
    // Modo local: usar la ruta base configurada
    define('IMAGES_FOLDER', IMAGES_BASE_PATH);
    define('IMAGES_DISK_MODE', 'local');
}

// Configuración de logs
define('LOGS_FOLDER', __DIR__ . '/../logs');

// Configuración de sincronización
define('SYNC_LIMIT', getenv('SYNC_LIMIT') ? (int)getenv('SYNC_LIMIT') : 0); // 0 = sin límite
define('DOWNLOAD_IMAGES', getenv('DOWNLOAD_IMAGES') ? (strtolower(getenv('DOWNLOAD_IMAGES')) === 'true') : true);
define('TRACK_CHANGES', getenv('TRACK_CHANGES') ? (strtolower(getenv('TRACK_CHANGES')) === 'true') : true);

// Configuración de depuración
define('DEBUG_MODE', getenv('DEBUG_MODE') ? (strtolower(getenv('DEBUG_MODE')) === 'true') : false);
define('APP_DEBUG', getenv('DEBUG_MODE') ? (strtolower(getenv('DEBUG_MODE')) === 'true') : false);
