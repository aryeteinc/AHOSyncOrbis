<?php
/**
 * Script para probar la carga de variables de entorno
 */

echo "Probando carga de variables de entorno...\n\n";

// Cargar variables de entorno manualmente
$envFile = __DIR__ . '/config/.env';
if (file_exists($envFile)) {
    echo "Leyendo archivo .env: $envFile\n";
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Ignorar comentarios
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parsear línea
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $value = trim($parts[1]);
            
            // Eliminar comillas
            if (strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) {
                $value = substr($value, 1, -1);
            }
            
            // Establecer variable de entorno
            putenv("$key=$value");
            echo "  $key = $value\n";
        }
    }
}

echo "\nValores cargados:\n";
echo "IMAGES_STORAGE_MODE = " . getenv('IMAGES_STORAGE_MODE') . "\n";
echo "LARAVEL_PUBLIC_PATH = " . getenv('LARAVEL_PUBLIC_PATH') . "\n";
echo "LARAVEL_DISK = " . getenv('LARAVEL_DISK') . "\n";
echo "LARAVEL_IMAGES_PATH = " . getenv('LARAVEL_IMAGES_PATH') . "\n";

// Cargar configuración
require_once __DIR__ . '/config/config.php';

echo "\nValores definidos en config.php:\n";
echo "IMAGES_STORAGE_MODE = " . IMAGES_STORAGE_MODE . "\n";
echo "LARAVEL_PUBLIC_PATH = " . LARAVEL_PUBLIC_PATH . "\n";
echo "LARAVEL_DISK = " . LARAVEL_DISK . "\n";
echo "LARAVEL_IMAGES_PATH = " . LARAVEL_IMAGES_PATH . "\n";
echo "IMAGES_FOLDER = " . IMAGES_FOLDER . "\n";
echo "IMAGES_DISK_MODE = " . IMAGES_DISK_MODE . "\n";
