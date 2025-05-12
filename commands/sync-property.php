<?php
/**
 * Script para sincronizar una propiedad específica por su referencia
 */

// Cargar configuración y variables de entorno
require_once __DIR__ . '/../src/EnvLoader.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/SchemaManager.php';
require_once __DIR__ . '/../src/PropertyProcessor.php';
require_once __DIR__ . '/../src/ImageProcessorLaravel.php';

// Verificar argumentos
if ($argc < 2) {
    echo "Uso: php sync-property.php <referencia>\n";
    echo "Ejemplo: php sync-property.php 250\n";
    exit(1);
}

$propertyRef = $argv[1];
echo "Sincronizando propiedad con referencia: {$propertyRef}\n";

// Conectar a la base de datos
try {
    $db = Database::getInstance();
    $connection = $db->getConnection();
    echo "Conexión a la base de datos establecida\n";
    
    // Verificar el esquema de base de datos
    $schemaManager = new SchemaManager($connection);
    $schemaType = 'laravel'; // Usamos el esquema de Laravel según las convenciones implementadas
    echo "Esquema de base de datos: {$schemaType}\n\n";
    
    // Verificar tablas necesarias
    echo "Verificando tablas necesarias...\n";
    $createTables = true; // Siempre crear tablas si no existen
    $tablesExist = $schemaManager->checkRequiredTables($createTables);
    
    if (!$tablesExist) {
        echo "Algunas tablas necesarias no existen\n";
    } else {
        echo "Todas las tablas necesarias existen\n";
    }
} catch (Exception $e) {
    echo "Error al conectar a la base de datos: " . $e->getMessage() . "\n";
    exit(1);
}

// Verificar carpeta de imágenes
$imagesFolder = getenv('IMAGES_FOLDER') ?: 'public/images/inmuebles';

// Si estamos en modo Laravel, usar la estructura de Laravel
if (getenv('IMAGES_STORAGE_MODE') === 'laravel') {
    $imagesFolder = '/Users/joseflorez/laravel/Probando/storage/app/public/images/inmuebles';
}

echo "Verificando carpeta de imágenes...\n";
if (!is_dir($imagesFolder)) {
    echo "Creando carpeta de imágenes: {$imagesFolder}\n";
    mkdir($imagesFolder, 0755, true);
}

if (!is_dir($imagesFolder)) {
    echo "Error: No se pudo crear la carpeta de imágenes\n";
    exit(1);
}

echo "Carpeta de imágenes configurada correctamente: {$imagesFolder}\n";

// Obtener datos de la API
$apiUrl = getenv('API_URL');
echo "Obteniendo datos de la API: {$apiUrl}\n";

$response = file_get_contents($apiUrl);
$data = json_decode($response, true);

// Verificar estructura de la respuesta
if (isset($data['data']) && is_array($data['data'])) {
    $properties = $data['data'];
    echo "Se encontraron " . count($properties) . " propiedades en la respuesta\n";
    
    // Buscar la propiedad específica
    $propertyFound = false;
    foreach ($properties as $property) {
        if ($property['ref'] == $propertyRef) {
            $propertyFound = true;
            echo "Propiedad #{$propertyRef} encontrada en la API\n";
            
            // Inicializar estadísticas
            $stats = [
                'inmuebles_procesados' => 0,
                'inmuebles_nuevos' => 0,
                'inmuebles_actualizados' => 0,
                'inmuebles_sin_cambios' => 0,
                'imagenes_descargadas' => 0,
                'imagenes_eliminadas' => 0,
                'errores' => 0
            ];
            
            // Procesar la propiedad
            $propertyProcessor = new PropertyProcessor(
                $connection,
                $imagesFolder,
                true, // Descargar imágenes
                true, // Registrar cambios
                $stats
            );
            
            try {
                echo "\nProcesando inmueble #{$propertyRef}\n";
                $propertyId = $propertyProcessor->processProperty($property);
                echo "Inmueble #{$propertyRef} procesado correctamente con ID: {$propertyId}\n";
                
                // Mostrar estadísticas
                echo "\nEstadísticas de sincronización:\n";
                echo "  Inmuebles procesados: " . $stats['inmuebles_procesados'] . "\n";
                echo "  Inmuebles nuevos: " . $stats['inmuebles_nuevos'] . "\n";
                echo "  Inmuebles actualizados: " . $stats['inmuebles_actualizados'] . "\n";
                echo "  Inmuebles sin cambios: " . $stats['inmuebles_sin_cambios'] . "\n";
                echo "  Imágenes descargadas: " . $stats['imagenes_descargadas'] . "\n";
                echo "  Imágenes eliminadas: " . $stats['imagenes_eliminadas'] . "\n";
                echo "  Errores: " . $stats['errores'] . "\n";
            } catch (Exception $e) {
                echo "Error al procesar propiedad: " . $e->getMessage() . "\n";
            }
            
            break;
        }
    }
    
    if (!$propertyFound) {
        echo "Error: No se encontró la propiedad con referencia {$propertyRef} en la API\n";
        exit(1);
    }
} else {
    echo "Error: Estructura de respuesta no reconocida\n";
    exit(1);
}
