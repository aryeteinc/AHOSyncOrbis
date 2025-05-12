<?php
/**
 * Script principal de sincronización optimizado para SyncOrbisPhp
 * 
 * Esta versión optimizada utiliza procesamiento por lotes, caché y otras
 * técnicas para mejorar significativamente la velocidad de sincronización.
 * 
 * Uso: php sync_optimized.php [opciones]
 * 
 * Opciones:
 *   --limit=N           Limitar a N inmuebles (0 = sin límite)
 *   --force             Forzar actualización incluso si no hay cambios
 *   --no-images         No descargar imágenes
 *   --batch-size=N      Tamaño del lote para procesamiento (por defecto: 50)
 *   --use-cache         Utilizar caché para mejorar rendimiento
 *   --cache-ttl=N       Tiempo de vida del caché en segundos (por defecto: 3600)
 *   --verbose           Mostrar información detallada
 */

// Iniciar medición de tiempo
$startTime = microtime(true);

// Cargar configuración y clases
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Synchronizer.php';
require_once __DIR__ . '/src/PropertyProcessor.php';
require_once __DIR__ . '/src/ImageProcessor.php';
require_once __DIR__ . '/src/BatchProcessor.php';
require_once __DIR__ . '/src/CacheManager.php';
require_once __DIR__ . '/src/Logger.php';

// Procesar opciones de línea de comandos
$options = getopt('', ['limit::', 'force', 'no-images', 'batch-size::', 'use-cache', 'cache-ttl::', 'verbose']);

$limit = isset($options['limit']) ? (int)$options['limit'] : 0;
$force = isset($options['force']);
$downloadImages = !isset($options['no-images']);
$batchSize = isset($options['batch-size']) ? (int)$options['batch-size'] : 50;
$useCache = isset($options['use-cache']);
$cacheTtl = isset($options['cache-ttl']) ? (int)$options['cache-ttl'] : 3600;
$verbose = isset($options['verbose']);

// Inicializar logger
$logger = new Logger('sync_optimized', $verbose);

// Mostrar banner
$logger->info('=== SYNCORBISPHP - SINCRONIZACIÓN OPTIMIZADA ===');
$logger->info('Iniciando proceso de sincronización...');

// Mostrar configuración
$logger->info('Configuración:');
$logger->info('- Límite de inmuebles: ' . ($limit > 0 ? $limit : 'Sin límite'));
$logger->info('- Forzar actualización: ' . ($force ? 'Sí' : 'No'));
$logger->info('- Descargar imágenes: ' . ($downloadImages ? 'Sí' : 'No'));
$logger->info('- Tamaño de lote: ' . $batchSize);
$logger->info('- Usar caché: ' . ($useCache ? 'Sí' : 'No'));
if ($useCache) {
    $logger->info('- TTL del caché: ' . $cacheTtl . ' segundos');
}

// Inicializar caché si está habilitado
$cacheManager = null;
if ($useCache) {
    $cacheDir = __DIR__ . '/cache';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    
    $cacheManager = new CacheManager($cacheDir, true, $cacheTtl, function($message) use ($logger) {
        $logger->debug($message);
    });
    
    $logger->info('Sistema de caché inicializado');
}

// Inicializar base de datos
try {
    $db = Database::getInstance();
    $connection = $db->getConnection();
    $logger->info('Conexión a la base de datos establecida');
} catch (Exception $e) {
    $logger->error('Error al conectar a la base de datos: ' . $e->getMessage());
    exit(1);
}

// Inicializar procesador por lotes
$batchProcessor = new BatchProcessor($connection, $batchSize, function($message) use ($logger) {
    $logger->info($message);
});

// Inicializar sincronizador
$synchronizer = new Synchronizer($connection, function($message) use ($logger) {
    $logger->info($message);
});

// Inicializar procesador de propiedades
$propertyProcessor = new PropertyProcessor($connection, function($message) use ($logger) {
    $logger->debug($message);
});

// Inicializar procesador de imágenes
$imageProcessor = new ImageProcessor(IMAGES_FOLDER, function($message) use ($logger) {
    $logger->debug($message);
});

// Verificar tablas necesarias
$logger->info('Verificando estructura de la base de datos...');
$tablesOk = $synchronizer->checkTables();

if (!$tablesOk) {
    $logger->error('Error: Faltan tablas necesarias en la base de datos.');
    $logger->info('Ejecute el script de reinicio para crear las tablas:');
    $logger->info('php commands/reset.php --confirm');
    exit(1);
}

// Obtener datos de la API
$logger->info('Obteniendo datos de la API...');

// Usar caché para la respuesta de la API si está habilitado
$apiData = null;
$cacheKey = 'api_data_' . md5(API_URL . '_' . $limit);

if ($useCache && !$force && $cacheManager->has($cacheKey)) {
    $logger->info('Usando datos de API en caché...');
    $apiData = $cacheManager->get($cacheKey);
} else {
    $logger->info('Descargando datos frescos de la API...');
    
    try {
        $apiData = $synchronizer->fetchDataFromApi($limit);
        
        // Guardar en caché si está habilitado
        if ($useCache && $apiData) {
            $cacheManager->set($cacheKey, $apiData);
        }
    } catch (Exception $e) {
        $logger->error('Error al obtener datos de la API: ' . $e->getMessage());
        exit(1);
    }
}

if (!$apiData) {
    $logger->error('No se pudieron obtener datos de la API.');
    exit(1);
}

$totalProperties = count($apiData);
$logger->info("Se encontraron {$totalProperties} inmuebles para sincronizar");

// Preparar estadísticas
$stats = [
    'total' => $totalProperties,
    'new' => 0,
    'updated' => 0,
    'unchanged' => 0,
    'errors' => 0,
    'images_downloaded' => 0,
    'images_errors' => 0
];

// Procesar inmuebles en lotes
$logger->info('Iniciando procesamiento por lotes...');

// Función para procesar cada propiedad
$processProperty = function($property) use ($propertyProcessor, $imageProcessor, $downloadImages, $force, &$stats, $logger) {
    try {
        // Procesar la propiedad
        $result = $propertyProcessor->processProperty($property, $force);
        
        if ($result['status'] === 'new') {
            $stats['new']++;
            $logger->debug("Nuevo inmueble creado: {$property['ref']}");
        } elseif ($result['status'] === 'updated') {
            $stats['updated']++;
            $logger->debug("Inmueble actualizado: {$property['ref']}");
        } elseif ($result['status'] === 'unchanged') {
            $stats['unchanged']++;
            $logger->debug("Inmueble sin cambios: {$property['ref']}");
        } else {
            $stats['errors']++;
            $logger->debug("Error al procesar inmueble: {$property['ref']}");
            return false;
        }
        
        // Procesar imágenes si está habilitado y la propiedad fue creada o actualizada
        if ($downloadImages && ($result['status'] === 'new' || $result['status'] === 'updated' || $force)) {
            if (isset($property['imagenes']) && is_array($property['imagenes'])) {
                $inmuebleId = $result['id'];
                
                foreach ($property['imagenes'] as $index => $imagen) {
                    try {
                        $imageResult = $imageProcessor->processImage($imagen, $inmuebleId, $property['ref'], $index);
                        
                        if ($imageResult) {
                            $stats['images_downloaded']++;
                        } else {
                            $stats['images_errors']++;
                        }
                    } catch (Exception $e) {
                        $stats['images_errors']++;
                        $logger->error("Error al procesar imagen: " . $e->getMessage());
                    }
                }
            }
        }
        
        return true;
    } catch (Exception $e) {
        $stats['errors']++;
        $logger->error("Error al procesar propiedad {$property['ref']}: " . $e->getMessage());
        return false;
    }
};

// Procesar propiedades en lotes
$batchResults = $batchProcessor->processBatch($apiData, $processProperty, true);

// Mostrar estadísticas finales
$logger->info('=== ESTADÍSTICAS DE SINCRONIZACIÓN ===');
$logger->info("Total de inmuebles procesados: {$stats['total']}");
$logger->info("- Nuevos: {$stats['new']}");
$logger->info("- Actualizados: {$stats['unchanged']}");
$logger->info("- Sin cambios: {$stats['unchanged']}");
$logger->info("- Errores: {$stats['errors']}");

if ($downloadImages) {
    $logger->info("Total de imágenes procesadas: " . ($stats['images_downloaded'] + $stats['images_errors']));
    $logger->info("- Descargadas correctamente: {$stats['images_downloaded']}");
    $logger->info("- Errores: {$stats['images_errors']}");
}

// Mostrar rendimiento
$totalTime = round(microtime(true) - $startTime, 2);
$itemsPerSecond = round($stats['total'] / $totalTime, 2);

$logger->info('=== RENDIMIENTO ===');
$logger->info("Tiempo total: {$totalTime} segundos");
$logger->info("Rendimiento: {$itemsPerSecond} inmuebles/segundo");
$logger->info("Rendimiento del procesamiento por lotes: {$batchResults['performance']} elementos/segundo");

// Limpiar caché expirado si está habilitado
if ($useCache) {
    $cacheManager->clearExpired();
    $cacheStats = $cacheManager->getStats();
    $logger->info('Estadísticas de caché:');
    $logger->info("- Archivos totales: {$cacheStats['total_files']}");
    $logger->info("- Tamaño total: " . round($cacheStats['total_size'] / 1024 / 1024, 2) . " MB");
}

// Finalizar
$logger->info('=== SINCRONIZACIÓN COMPLETADA ===');
echo "\n";
echo "=================================================\n";
echo "  SINCRONIZACIÓN COMPLETADA EXITOSAMENTE\n";
echo "=================================================\n";
echo "\n";
echo "Estadísticas:\n";
echo "- Total de inmuebles procesados: {$stats['total']}\n";
echo "- Nuevos: {$stats['new']}\n";
echo "- Actualizados: {$stats['updated']}\n";
echo "- Sin cambios: {$stats['unchanged']}\n";
echo "- Errores: {$stats['errors']}\n";
echo "\n";
echo "Tiempo total: {$totalTime} segundos\n";
echo "Rendimiento: {$itemsPerSecond} inmuebles/segundo\n";
echo "\n";
