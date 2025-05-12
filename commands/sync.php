<?php
/**
 * Script de sincronización de propiedades inmobiliarias
 * Uso: php commands/sync.php [opciones]
 */

// Cargar clases necesarias
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/PropertyProcessor.php';
require_once __DIR__ . '/../src/SchemaManager.php';
require_once __DIR__ . '/../src/ImageProcessor.php';

// Iniciar tiempo de ejecución
$startTime = microtime(true);

// Configurar opciones de línea de comandos
$options = getopt('l:f:r:s:', ['limit:', 'force', 'no-images', 'reset', 'help', 'ref:', 'schema:', 'create-tables']);

// Mostrar ayuda si se solicita
if (isset($options['help'])) {
    echo "Uso: php sync.php [opciones]\n";
    echo "Opciones:\n";
    echo "  -l, --limit=N       Limitar a N propiedades (predeterminado: sin límite)\n";
    echo "  -f, --force         Forzar sincronización completa (ignorar hashes)\n";
    echo "  -r, --ref=REF       Sincronizar solo el inmueble con la referencia especificada\n";
    echo "  -s, --schema=NAME   Usar un esquema específico (laravel, spanish, custom)\n";
    echo "  --no-images         No descargar imágenes\n";
    echo "  --reset             Restablecer la base de datos antes de sincronizar\n";
    echo "  --create-tables     Crear tablas automáticamente si no existen\n";
    echo "  --help              Mostrar esta ayuda\n";
    exit(0);
}

// Procesar opciones
$limit = $options['l'] ?? $options['limit'] ?? (defined('SYNC_LIMIT') ? SYNC_LIMIT : 0);
$force = isset($options['f']) || isset($options['force']);
$downloadImages = !isset($options['no-images']);
$reset = isset($options['reset']);
$createTables = isset($options['create-tables']);
$specificRef = $options['r'] ?? $options['ref'] ?? null;
$schemaName = $options['s'] ?? $options['schema'] ?? null;

// Si se especifica una referencia, mostrar información
if ($specificRef) {
    echo "Se sincronizará solo el inmueble con referencia: {$specificRef}\n";
}

// Inicializar base de datos
try {
    $db = Database::getInstance();
    $connection = $db->getConnection();
    echo "Conexión a la base de datos establecida\n";
} catch (Exception $e) {
    echo "Error al conectar a la base de datos: " . $e->getMessage() . "\n";
    exit(1);
}

// Inicializar el gestor de esquemas
try {
    $schemaManager = new SchemaManager($connection, $schemaName);
    echo "Esquema de base de datos: " . $schemaManager->getSchemaName() . "\n";
} catch (Exception $e) {
    echo "Error al inicializar el gestor de esquemas: " . $e->getMessage() . "\n";
    exit(1);
}

// Restablecer la base de datos si se solicita
if ($reset) {
    echo "Restableciendo la base de datos...\n";
    require_once __DIR__ . '/delete-db.php';
    $createTables = true; // Si se reinicia, hay que crear las tablas
}

echo "\n======================================================================\n";
echo "SINCRONIZACIÓN DE DATOS INMOBILIARIOS (PHP)\n";
echo "======================================================================\n\n";

echo "Iniciando sincronización...\n";

// Verificar tablas necesarias
echo "Verificando tablas necesarias...\n";
$requiredTables = ['properties', 'images'];
$allTablesExist = $schemaManager->checkRequiredTables($requiredTables);

if (!$allTablesExist) {
    if ($createTables) {
        echo "Creando tablas necesarias...\n";
        $tablesCreated = $schemaManager->createRequiredTables($requiredTables);
        echo "Se han creado {$tablesCreated} tablas.\n";
    } else {
        echo "ERROR: Faltan tablas necesarias. Ejecute el script con --create-tables para crearlas automáticamente.\n";
        exit(1);
    }
}

// Verificar nuevamente después de crear tablas
if ($schemaManager->checkRequiredTables($requiredTables)) {
    echo "Todas las tablas necesarias existen\n";
} else {
    echo "ERROR: No se pudieron crear todas las tablas necesarias.\n";
    exit(1);
}

// Obtener la ruta de la carpeta de imágenes desde las variables de entorno
$imagesStorageMode = getenv('IMAGES_STORAGE_MODE') ?: 'local';

if ($imagesStorageMode === 'laravel') {
    $laravelStoragePath = getenv('LARAVEL_STORAGE_PATH') ?: __DIR__ . '/../storage/app/public';
    $laravelImagesPath = getenv('LARAVEL_IMAGES_PATH') ?: 'images/inmuebles';
    $imagesFolder = $laravelStoragePath . '/' . $laravelImagesPath;
} else {
    $imagesFolder = getenv('IMAGES_FOLDER') ? __DIR__ . '/../' . getenv('IMAGES_FOLDER') : __DIR__ . '/../public/images/inmuebles';
}

// Verificar carpeta de imágenes
echo "Verificando carpeta de imágenes...\n";
if (!file_exists($imagesFolder)) {
    if (!mkdir($imagesFolder, 0755, true)) {
        echo "ERROR: No se pudo crear la carpeta de imágenes: {$imagesFolder}\n";
        exit(1);
    }
}
echo "Carpeta de imágenes configurada correctamente: {$imagesFolder}\n";

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

// Obtener datos de la API desde las variables de entorno
$apiUrl = getenv('API_URL') ?: 'https://api.example.com/';
echo "Obteniendo datos de la API: {$apiUrl}\n";

try {
    // Configurar opciones para la solicitud HTTP
    $context = stream_context_create([
        'http' => [
            'timeout' => 30, // Timeout de 30 segundos
            'user_agent' => 'SyncOrbisPhp/1.0'
        ]
    ]);
    
    echo "Realizando solicitud a la API...\n";
    $response = file_get_contents($apiUrl, false, $context);
    
    if ($response === false) {
        throw new Exception("No se pudo obtener respuesta de la API. Verifique la URL y la conexión a Internet.");
    }
    
    echo "Respuesta recibida. Analizando datos...\n";
    $data = json_decode($response, true);
    
    if ($data === null) {
        echo "Error al decodificar JSON. Respuesta recibida:\n";
        echo substr($response, 0, 1000) . (strlen($response) > 1000 ? '...' : '') . "\n";
        throw new Exception("Error al decodificar JSON. Verifique el formato de la respuesta.");
    }
    
    // Basado en el análisis de sync-debug.php, sabemos que la estructura es:
    // $data['data'] = array de inmuebles
    if (isset($data['data']) && is_array($data['data'])) {
        echo "Estructura de respuesta detectada: array de inmuebles en 'data'\n";
        $properties = $data['data'];
    } else if (isset($data['inmuebles']) && is_array($data['inmuebles'])) {
        // Estructura alternativa: inmuebles directamente en la raíz
        echo "Estructura de respuesta detectada: array de inmuebles en 'inmuebles'\n";
        $properties = $data['inmuebles'];
    } else {
        // Intentar encontrar cualquier array que pueda contener inmuebles
        echo "Estructura de datos inesperada. Explorando respuesta...\n";
        
        // Buscar cualquier array que pueda contener inmuebles
        $foundProperties = false;
        foreach ($data as $key => $value) {
            if (is_array($value) && !empty($value)) {
                // Verificar si el primer elemento parece ser un inmueble
                $firstElement = reset($value);
                if (is_array($firstElement) && (isset($firstElement['ref']) || isset($firstElement['referencia']))) {
                    echo "Encontrado array de inmuebles en '{$key}'\n";
                    $properties = $value;
                    $foundProperties = true;
                    break;
                }
            }
        }
        
        if (!$foundProperties) {
            echo "No se encontró ningún array que parezca contener inmuebles. Claves disponibles:\n";
            print_r(array_keys($data));
            throw new Exception("Formato de respuesta inválido. No se pudo determinar dónde están los datos de inmuebles.");
        }
    }
    $totalProperties = count($properties);
    
    if ($limit > 0 && $limit < $totalProperties) {
        echo "Limitando a {$limit} propiedades de {$totalProperties} disponibles\n";
        $properties = array_slice($properties, 0, $limit);
    }
    
    $propertiesToProcess = count($properties);
    echo "Se procesarán {$propertiesToProcess} propiedades\n";
    
    // Filtrar por referencia específica si se solicita
    if ($specificRef) {
        $filteredProperties = [];
        foreach ($properties as $property) {
            if ($property['referencia'] == $specificRef) {
                $filteredProperties[] = $property;
                break;
            }
        }
        
        if (empty($filteredProperties)) {
            echo "ERROR: No se encontró ninguna propiedad con la referencia {$specificRef}\n";
            exit(1);
        }
        
        $properties = $filteredProperties;
        $propertiesToProcess = 1;
    }
    
    echo "Se procesarán {$propertiesToProcess} propiedades\n\n";
    
    // Inicializar procesador de propiedades
    $propertyProcessor = new PropertyProcessor($connection, $imagesFolder, $downloadImages, true, $stats);
    
    // Procesar cada propiedad
    $count = 0;
    foreach ($properties as $property) {
        $count++;
        $percentage = round(($count / $propertiesToProcess) * 100);
        
        $ref = isset($property['ref']) ? $property['ref'] : '';
        echo "\nProcesando inmueble #{$count}/{$propertiesToProcess} ({$percentage}%): Ref {$ref}\n";
        
        try {
            // Normalizar datos de la API al formato de la base de datos
            // Basado en la estructura real de la API
            $normalizedProperty = [
                'ref' => $property['ref'] ?? '',
                'sync_code' => $property['codigo_consignacion_sincronizacion'] ?? null,
                'title' => 'Inmueble Ref. ' . ($property['ref'] ?? ''),
                'description' => $property['observacion_portales'] ?? $property['observacion'] ?? '',
                'address' => $property['direccion'] ?? '',
                'sale_price' => $property['valor_venta'] ?? 0,
                'rent_price' => $property['valor_canon'] ?? 0,
                'built_area' => $property['area_construida'] ?? 0,
                'private_area' => $property['area_libre'] ?? 0,
                'total_area' => $property['area_total'] ?? 0,
                'bedrooms' => $property['alcobas'] ?? 0,
                'bathrooms' => $property['baños'] ?? 0,
                'garages' => 0, // No se encuentra en la API
                'stratum' => $property['estrato'] ?? 0,
                'age' => $property['antiguedad'] ?? 0,
                'floor' => 0, // No se encuentra en la API
                'has_elevator' => 0, // No se encuentra en la API
                'administration_fee' => $property['valor_admon'] ?? 0,
                'latitude' => $property['latitud'] ?? 0,
                'longitude' => $property['longitud'] ?? 0,
                'city' => $property['ciudad'] ?? '',
                'neighborhood' => $property['barrio'] ?? '',
                'property_type' => $property['tipo_inmueble'] ?? '',
                'property_use' => $property['uso'] ?? '',
                'property_status' => $property['estado_actual'] ?? '',
                'consignment_type' => $property['tipo_consignacion'] ?? '',
                'images' => $property['imagenes'] ?? [],
                'characteristics' => $property['caracteristicas'] ?? []
            ];
            
            // Generar una descripción corta
            $shortDescription = substr(strip_tags($normalizedProperty['description']), 0, 150);
            if (strlen($normalizedProperty['description']) > 150) {
                $shortDescription .= '...';
            }
            $normalizedProperty['short_description'] = $shortDescription;
            
            // Procesar la propiedad
            $propertyId = $propertyProcessor->processProperty($normalizedProperty);
            
        } catch (Exception $e) {
            echo "Error al procesar propiedad: " . $e->getMessage() . "\n";
            $stats['errores']++;
        }
    }
    
    // Mostrar resumen
    echo "\n======================================================================\n";
    echo "RESUMEN DE SINCRONIZACIÓN\n";
    echo "======================================================================\n";
    echo "Inicio: " . date('Y-m-d H:i:s', floor($startTime)) . "\n";
    echo "Fin: " . date('Y-m-d H:i:s') . "\n";
    echo "Duración: " . round(microtime(true) - $startTime, 2) . " segundos\n\n";
    
    echo "Inmuebles procesados: " . $stats['inmuebles_procesados'] . "\n";
    echo "Inmuebles nuevos: " . $stats['inmuebles_nuevos'] . "\n";
    echo "Inmuebles actualizados: " . $stats['inmuebles_actualizados'] . "\n";
    echo "Inmuebles sin cambios: " . $stats['inmuebles_sin_cambios'] . "\n";
    echo "Imágenes descargadas: " . $stats['imagenes_descargadas'] . "\n";
    echo "Imágenes eliminadas: " . $stats['imagenes_eliminadas'] . "\n";
    echo "Errores: " . $stats['errores'] . "\n";
    echo "======================================================================\n";
    
} catch (Exception $e) {
    echo "Error durante la sincronización: " . $e->getMessage() . "\n";
    exit(1);
}

// Mostrar tiempo total
echo "\nSincronización completada en " . round(microtime(true) - $startTime, 2) . " segundos\n";
echo "Resultados:\n";
echo "  Inmuebles procesados: " . $stats['inmuebles_procesados'] . "\n";
echo "  Inmuebles nuevos: " . $stats['inmuebles_nuevos'] . "\n";
echo "  Inmuebles actualizados: " . $stats['inmuebles_actualizados'] . "\n";
echo "  Inmuebles sin cambios: " . $stats['inmuebles_sin_cambios'] . "\n";
echo "  Imágenes descargadas: " . $stats['imagenes_descargadas'] . "\n";
echo "  Imágenes eliminadas: " . $stats['imagenes_eliminadas'] . "\n";
echo "  Errores: " . $stats['errores'] . "\n";
