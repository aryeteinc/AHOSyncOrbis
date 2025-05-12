<?php
/**
 * Script para probar la sincronización de inmuebles
 * Este script prueba la sincronización de un inmueble específico para verificar
 * que todos los campos se manejen correctamente
 */

// Cargar configuración
require_once __DIR__ . '/../config/config.php';

// Cargar clases necesarias
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/PropertyProcessor.php';
require_once __DIR__ . '/../src/ImageProcessor.php';

// Obtener conexión a la base de datos
$db = Database::getInstance()->getConnection();

// Configuración
$imagesFolder = IMAGES_FOLDER;
if (!is_dir($imagesFolder)) {
    mkdir($imagesFolder, 0755, true);
    echo "Carpeta de imágenes creada: {$imagesFolder}\n";
}

// Estadísticas
$stats = [
    'inmuebles_procesados' => 0,
    'inmuebles_nuevos' => 0,
    'inmuebles_actualizados' => 0,
    'inmuebles_sin_cambios' => 0,
    'imagenes_descargadas' => 0,
    'imagenes_eliminadas' => 0,
    'errores' => 0
];

// Crear instancia del procesador de propiedades
$propertyProcessor = new PropertyProcessor($db, $imagesFolder, true, true, $stats);

// Función para obtener datos de prueba
function getTestProperty() {
    // Datos de prueba para un inmueble
    return [
        'ref' => 'TEST001',
        'codigo_sincronizacion' => 'CS001',
        'titulo' => 'Apartamento de prueba',
        'descripcion' => 'Este es un apartamento de prueba para verificar la sincronización. Cuenta con excelentes acabados, buena ubicación y todas las comodidades necesarias.',
        'descripcion_corta' => 'Apartamento de prueba con excelentes acabados',
        'direccion' => 'Calle 123 #45-67',
        'precio_venta' => 250000000,
        'precio_arriendo' => 1500000,
        'administracion' => 350000,
        'precio_total' => 250000000,
        'area_construida' => 80.5,
        'area_privada' => 75.2,
        'area_total' => 80.5,
        'area_terreno' => 0,
        'habitaciones' => 3,
        'banos' => 2,
        'garajes' => 1,
        'estrato' => 4,
        'antiguedad' => 5,
        'piso' => 3,
        'ascensor' => 1,
        'destacado' => 1,
        'activo' => 1,
        'en_caliente' => 0,
        'latitud' => 4.6097100,
        'longitud' => -74.0817500,
        'ciudad_nombre' => 'Bogotá',
        'barrio_nombre' => 'Chapinero',
        'tipo_inmueble_nombre' => 'Apartamento',
        'uso_inmueble_nombre' => 'Vivienda',
        'estado_inmueble_nombre' => 'Disponible',
        'tipo_consignacion_nombre' => 'Venta y Arriendo',
        'caracteristicas' => [
            ['nombre' => 'Vigilancia', 'valor' => 'Si'],
            ['nombre' => 'Parqueadero Visitantes', 'valor' => 'Si'],
            ['nombre' => 'Zona BBQ', 'valor' => 'Si'],
            ['nombre' => 'Gimnasio', 'valor' => 'Si'],
            ['nombre' => 'Piscina', 'valor' => 'No']
        ],
        'imagenes' => [
            'https://picsum.photos/800/600?random=1',
            'https://picsum.photos/800/600?random=2',
            'https://picsum.photos/800/600?random=3'
        ]
    ];
}

// Obtener datos de prueba
$testProperty = getTestProperty();

echo "Iniciando prueba de sincronización...\n";
echo "Procesando inmueble de prueba: {$testProperty['ref']}\n";

try {
    // Procesar la propiedad
    $propertyProcessor->processProperty($testProperty);
    
    // Mostrar estadísticas
    echo "\nEstadísticas de la prueba:\n";
    foreach ($stats as $key => $value) {
        echo "- {$key}: {$value}\n";
    }
    
    // Verificar si el inmueble se creó o actualizó correctamente
    $stmt = $db->prepare("SELECT * FROM inmuebles WHERE ref = ?");
    $stmt->execute([$testProperty['ref']]);
    $property = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($property) {
        echo "\nInmueble encontrado en la base de datos:\n";
        echo "- ID: {$property['id']}\n";
        echo "- Ref: {$property['ref']}\n";
        echo "- Código Sincronización: {$property['codigo_sincronizacion']}\n";
        echo "- Título: {$property['titulo']}\n";
        echo "- Slug: {$property['slug']}\n";
        echo "- Precio Venta: {$property['precio_venta']}\n";
        echo "- Precio Arriendo: {$property['precio_arriendo']}\n";
        echo "- Activo: {$property['activo']}\n";
        
        // Verificar si existe la tabla de características
        $tableExists = $db->query("SHOW TABLES LIKE 'caracteristicas_inmueble'")->rowCount() > 0;
        
        if ($tableExists) {
            // Verificar características
            $stmt = $db->prepare("SELECT * FROM caracteristicas_inmueble WHERE inmueble_id = ?");
            $stmt->execute([$property['id']]);
            $characteristics = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "\nCaracterísticas del inmueble:\n";
            foreach ($characteristics as $characteristic) {
                echo "- {$characteristic['nombre']}: {$characteristic['valor']}\n";
            }
        } else {
            echo "\nLa tabla 'caracteristicas_inmueble' no existe en la base de datos.\n";
        }
        
        // Verificar si existe la tabla de imágenes
        $tableExists = $db->query("SHOW TABLES LIKE 'imagenes'")->rowCount() > 0;
        
        if ($tableExists) {
            // Verificar imágenes
            $stmt = $db->prepare("SELECT * FROM imagenes WHERE inmueble_id = ? ORDER BY orden");
            $stmt->execute([$property['id']]);
            $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "\nImágenes del inmueble:\n";
            foreach ($images as $image) {
                echo "- ID: {$image['id']}, URL: {$image['url']}, Local: {$image['url_local']}\n";
            }
        } else {
            echo "\nLa tabla 'imagenes' no existe en la base de datos.\n";
        }
    } else {
        echo "\nERROR: No se encontró el inmueble en la base de datos.\n";
    }
    
    echo "\nPrueba de sincronización completada.\n";
} catch (Exception $e) {
    echo "Error durante la prueba: " . $e->getMessage() . "\n";
    echo "Traza: " . $e->getTraceAsString() . "\n";
}
