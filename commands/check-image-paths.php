<?php
/**
 * Script para verificar cómo se están guardando los campos laravel_disk y laravel_path en la base de datos
 */

// Cargar configuración y variables de entorno
require_once __DIR__ . '/../src/EnvLoader.php';
EnvLoader::load(__DIR__ . '/../config/.env');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Database.php';

echo "======================================================================\n";
echo "VERIFICACIÓN DE RUTAS DE IMÁGENES EN LA BASE DE DATOS\n";
echo "======================================================================\n\n";

// Conectar a la base de datos
try {
    $db = Database::getInstance();
    $connection = $db->getConnection();
    echo "Conexión a la base de datos establecida\n\n";
    
    // Consultar las imágenes en la base de datos
    $stmt = $connection->query("SELECT id, property_id, laravel_disk, laravel_path, local_url FROM images LIMIT 10");
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($images)) {
        echo "No se encontraron imágenes en la base de datos.\n";
        exit(0);
    }
    
    echo "Se encontraron " . count($images) . " imágenes en la base de datos.\n\n";
    
    echo "EJEMPLOS DE RUTAS DE IMÁGENES:\n";
    echo "----------------------------------------------------------------------\n";
    foreach ($images as $image) {
        echo "ID: {$image['id']}, Property ID: {$image['property_id']}\n";
        echo "  Laravel Disk: " . ($image['laravel_disk'] ?: 'NULL') . "\n";
        echo "  Laravel Path: " . ($image['laravel_path'] ?: 'NULL') . "\n";
        echo "  Local URL: " . ($image['local_url'] ?: 'NULL') . "\n";
        
        // Verificar si el archivo existe físicamente
        if (!empty($image['local_url']) && file_exists($image['local_url'])) {
            echo "  El archivo existe físicamente: SÍ\n";
        } else {
            echo "  El archivo existe físicamente: NO\n";
        }
        
        // Construir la URL para Laravel
        if (!empty($image['laravel_disk']) && !empty($image['laravel_path'])) {
            echo "  URL para Laravel (asset): {{ asset('storage/{$image['laravel_path']}') }}\n";
            echo "  URL para Laravel (Storage): {{ Storage::disk('{$image['laravel_disk']}')->url('{$image['laravel_path']}') }}\n";
        } else {
            echo "  URL para Laravel: No se puede construir (faltan datos)\n";
        }
        
        echo "----------------------------------------------------------------------\n";
    }
    
} catch (Exception $e) {
    echo "Error al conectar a la base de datos: " . $e->getMessage() . "\n";
    exit(1);
}
