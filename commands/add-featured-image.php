<?php
/**
 * Script para agregar el campo is_featured a la tabla images
 * y configurar la imagen principal por defecto
 */

// Cargar configuración y variables de entorno
require_once __DIR__ . '/../src/EnvLoader.php';
EnvLoader::load(__DIR__ . '/../config/.env');
require_once __DIR__ . '/../src/Database.php';

echo "======================================================================\n";
echo "AGREGAR CAMPO DE IMAGEN DESTACADA (is_featured)\n";
echo "======================================================================\n\n";

try {
    // Obtener conexión a la base de datos
    $db = Database::getInstance();
    $connection = $db->getConnection();
    
    // Verificar si la tabla images existe
    $tableExists = $connection->query("SHOW TABLES LIKE 'images'")->rowCount() > 0;
    
    if (!$tableExists) {
        echo "La tabla 'images' no existe. Ejecute primero sync-complete.php para crear las tablas.\n";
        exit(1);
    }
    
    // Verificar si el campo is_featured ya existe
    $columnExists = $connection->query("SHOW COLUMNS FROM images LIKE 'is_featured'")->rowCount() > 0;
    
    if ($columnExists) {
        echo "El campo 'is_featured' ya existe en la tabla 'images'.\n";
    } else {
        // Agregar el campo is_featured
        echo "Agregando campo 'is_featured' a la tabla 'images'...\n";
        $connection->exec("ALTER TABLE images ADD COLUMN is_featured TINYINT(1) DEFAULT 0 AFTER is_downloaded");
        echo "Campo 'is_featured' agregado correctamente.\n";
    }
    
    // Configurar imágenes destacadas por defecto (las que tienen order_num = 0)
    echo "\nConfigurando imágenes destacadas por defecto...\n";
    
    // Obtener todas las propiedades
    $stmt = $connection->query("SELECT id FROM properties");
    $properties = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $totalProperties = count($properties);
    $propertiesWithFeaturedImage = 0;
    
    foreach ($properties as $propertyId) {
        // Verificar si ya tiene una imagen destacada
        $hasFeatured = $connection->query("SELECT COUNT(*) FROM images WHERE property_id = $propertyId AND is_featured = 1")->fetchColumn();
        
        if ($hasFeatured > 0) {
            // Ya tiene una imagen destacada, respetarla
            continue;
        }
        
        // Buscar la imagen con order_num = 0 o la primera imagen disponible
        $sql = "SELECT id FROM images WHERE property_id = $propertyId ORDER BY order_num = 0 DESC, order_num ASC, id ASC LIMIT 1";
        $imageId = $connection->query($sql)->fetchColumn();
        
        if ($imageId) {
            // Marcar esta imagen como destacada
            $connection->exec("UPDATE images SET is_featured = 1 WHERE id = $imageId");
            $propertiesWithFeaturedImage++;
        }
    }
    
    echo "Se configuraron imágenes destacadas para $propertiesWithFeaturedImage de $totalProperties propiedades.\n";
    
    echo "\nProceso completado exitosamente.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
