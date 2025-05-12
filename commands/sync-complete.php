<?php
/**
 * Script completo para sincronizar propiedades inmobiliarias
 * Incluye creación automática de tablas y manejo de imágenes
 */

/**
 * Verifica si una tabla existe en la base de datos
 * 
 * @param PDO $connection Conexión a la base de datos
 * @param string $tableName Nombre de la tabla
 * @return bool True si la tabla existe, false en caso contrario
 */
function tableExists($connection, $tableName) {
    return $connection->query("SHOW TABLES LIKE '{$tableName}'")->rowCount() > 0;
}

/**
 * Elimina todas las imágenes de una carpeta y las subcarpetas vacías
 * 
 * @param string $folder Carpeta de imágenes
 * @return array Array con el número de archivos y carpetas eliminados
 */
function deleteAllImages($folder) {
    $countFiles = 0;
    $countDirs = 0;
    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
    
    if (!is_dir($folder)) {
        return ['files' => 0, 'directories' => 0];
    }
    
    // Primero recolectamos todas las carpetas en un array para procesarlas después
    $directories = [];
    
    $it = new RecursiveDirectoryIterator($folder, RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
    
    // Eliminar todos los archivos de imagen primero
    foreach ($files as $fileinfo) {
        if ($fileinfo->isFile()) {
            $extension = strtolower(pathinfo($fileinfo->getFilename(), PATHINFO_EXTENSION));
            
            if (in_array($extension, $imageExtensions)) {
                if (unlink($fileinfo->getRealPath())) {
                    $countFiles++;
                }
            }
        } elseif ($fileinfo->isDir()) {
            // Guardar la ruta de la carpeta para eliminarla después si está vacía
            $directories[] = $fileinfo->getRealPath();
        }
    }
    
    // Ordenar las carpetas por profundidad (de más profunda a menos profunda)
    // para asegurarnos de eliminar primero las subcarpetas
    usort($directories, function($a, $b) {
        return substr_count($b, DIRECTORY_SEPARATOR) - substr_count($a, DIRECTORY_SEPARATOR);
    });
    
    // Eliminar carpetas vacías (excepto la carpeta principal)
    foreach ($directories as $dir) {
        // Verificar que no sea la carpeta principal
        if ($dir !== $folder) {
            // Verificar si la carpeta está vacía
            if (is_dir($dir) && count(scandir($dir)) <= 2) { // . y .. siempre están presentes
                if (rmdir($dir)) {
                    $countDirs++;
                }
            }
        }
    }
    
    return ['files' => $countFiles, 'directories' => $countDirs];
}

// Cargar configuración y variables de entorno
require_once __DIR__ . '/../src/EnvLoader.php';
EnvLoader::load(__DIR__ . '/../config/.env'); // Asegurarse de cargar el archivo .env explícitamente
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/SchemaManager.php';
require_once __DIR__ . '/../src/PropertyProcessor.php';
require_once __DIR__ . '/../src/ImageProcessorLaravel.php';

// Definir opciones de línea de comandos
$options = getopt('', ['limit::', 'property::', 'reset', 'help', 'no-sync']);

// Mostrar ayuda si se solicita
if (isset($options['help'])) {
    echo "Uso: php sync-complete.php [opciones]\n";
    echo "Opciones:\n";
    echo "  --limit=N         Limitar a N propiedades\n";
    echo "  --property=REF    Sincronizar solo la propiedad con referencia REF\n";
    echo "  --reset           Eliminar todas las tablas antes de sincronizar\n";
    echo "  --no-sync         Solo crear las tablas sin sincronizar datos\n";
    echo "  --help            Mostrar esta ayuda\n";
    exit(0);
}

// Iniciar temporizador
$startTime = microtime(true);

echo "======================================================================\n";
echo "SINCRONIZACIÓN COMPLETA DE DATOS INMOBILIARIOS (PHP)\n";
echo "======================================================================\n\n";

// Obtener el modo de almacenamiento de imágenes
$storageMode = getenv('IMAGES_STORAGE_MODE') ?: 'local';

// Definir la carpeta de imágenes según el modo de almacenamiento
if ($storageMode === 'laravel') {
    // Modo Laravel: usar las variables de entorno de Laravel
    $laravelStoragePath = getenv('LARAVEL_STORAGE_PATH');
    $laravelImagesPath = getenv('LARAVEL_IMAGES_PATH');
    
    if ($laravelStoragePath && $laravelImagesPath) {
        $imagesFolder = rtrim($laravelStoragePath, '/') . '/' . trim($laravelImagesPath, '/');
    } else {
        // Ruta por defecto para Laravel si no se especifican las variables de entorno
        $imagesFolder = '/Users/joseflorez/laravel/Probando/storage/app/public/images/inmuebles';
    }
    
    echo "Modo de almacenamiento: Laravel\n";
} else {
    // Modo local: usar la variable de entorno IMAGES_FOLDER
    $imagesFolder = getenv('IMAGES_FOLDER') ?: 'public/images/inmuebles';
    echo "Modo de almacenamiento: Local\n";
}

echo "Carpeta de imágenes: {$imagesFolder}\n\n";

// Conectar a la base de datos
try {
    $db = Database::getInstance();
    $connection = $db->getConnection();
    echo "Conexión a la base de datos establecida\n";
} catch (Exception $e) {
    echo "Error al conectar a la base de datos: " . $e->getMessage() . "\n";
    exit(1);
}

// Eliminar tablas si se solicita
if (isset($options['reset'])) {
    echo "Eliminando todas las tablas de la base de datos...\n";
    
    // Obtener todas las tablas existentes
    $tables = [];
    $result = $connection->query("SHOW TABLES");
    while ($row = $result->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    
    if (empty($tables)) {
        echo "No hay tablas para eliminar\n";
    } else {
        echo "Se eliminarán las siguientes tablas:\n";
        foreach ($tables as $table) {
            echo "- $table\n";
        }
        
        // Desactivar restricciones de clave foránea
        $connection->exec("SET FOREIGN_KEY_CHECKS = 0");
        
        // Eliminar cada tabla
        foreach ($tables as $table) {
            $connection->exec("DROP TABLE `$table`");
            echo "Tabla `$table` eliminada.\n";
        }
        
        // Reactivar restricciones de clave foránea
        $connection->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        echo "\nTodas las tablas han sido eliminadas correctamente.\n\n";
    }
    
    // Eliminar todas las imágenes
    echo "Eliminando imágenes físicas...\n";
    if (is_dir($imagesFolder)) {
        echo "Buscando imágenes en: $imagesFolder\n";
        $deleted = deleteAllImages($imagesFolder);
        echo "Se eliminaron {$deleted['files']} imágenes y {$deleted['directories']} carpetas vacías de la carpeta $imagesFolder\n\n";
    } else {
        echo "La carpeta de imágenes no existe: $imagesFolder\n\n";
    }
}



// Crear instancia del SchemaManager
$schemaManager = new SchemaManager($connection);
$schemaType = 'laravel'; // Usamos el esquema de Laravel según las convenciones implementadas
echo "Esquema de base de datos: {$schemaType}\n\n";

// Verificar si las tablas necesarias existen
$propertiesExists = tableExists($connection, 'properties');
$propertyStatesExists = tableExists($connection, 'property_states');
$imagesExists = tableExists($connection, 'images');
$citiesExists = tableExists($connection, 'cities');
$neighborhoodsExists = tableExists($connection, 'neighborhoods');
$propertyTypesExists = tableExists($connection, 'property_types');
$propertyUsesExists = tableExists($connection, 'property_uses');
$consignmentTypesExists = tableExists($connection, 'consignment_types');
$characteristicsExists = tableExists($connection, 'characteristics');
$propertyCharacteristicsExists = tableExists($connection, 'property_characteristics');

// Crear las tablas si no existen
// Primero crear property_states ya que properties tiene una clave foránea que la referencia
if (!$propertyStatesExists) {
    echo "Creando tabla 'property_states'...\n";
    $connection->exec("
        CREATE TABLE property_states (
            id INT AUTO_INCREMENT PRIMARY KEY,
            property_id INT NULL,
            sync_code VARCHAR(100) NULL,
            name VARCHAR(100) NOT NULL,
            is_active TINYINT(1) DEFAULT 1,
            is_featured TINYINT(1) DEFAULT 0,
            is_hot TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (property_id),
            INDEX (sync_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Insertar estados predeterminados
    $connection->exec("
        INSERT INTO property_states (name) VALUES 
        ('Disponible'),
        ('Vendido'),
        ('Arrendado'),
        ('Reservado'),
        ('En construcción')
    ");
    
    echo "Tabla 'property_states' creada correctamente\n";
}

if (!$propertiesExists) {
    echo "Creando tabla 'properties'...\n";
    $connection->exec("
        CREATE TABLE properties (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ref VARCHAR(50) NULL,
            sync_code VARCHAR(100) NULL,
            title VARCHAR(255) NULL,
            description TEXT NULL,
            short_description TEXT NULL,
            address VARCHAR(255) NULL,
            sale_price DECIMAL(15,2) NULL DEFAULT 0,
            rent_price DECIMAL(15,2) NULL DEFAULT 0,
            administration_fee DECIMAL(15,2) NULL DEFAULT 0,
            built_area DECIMAL(10,2) NULL DEFAULT 0,
            private_area DECIMAL(10,2) NULL DEFAULT 0,
            total_area DECIMAL(10,2) NULL DEFAULT 0,
            land_area DECIMAL(10,2) NULL DEFAULT 0,
            bedrooms INT NULL DEFAULT 0,
            bathrooms INT NULL DEFAULT 0,
            garages INT NULL DEFAULT 0,
            stratum INT NULL DEFAULT 0,
            age INT NULL DEFAULT 0,
            floor INT NULL DEFAULT 0,
            has_elevator TINYINT(1) NULL DEFAULT 0,
            is_featured TINYINT(1) NULL DEFAULT 0,
            is_active TINYINT(1) NULL DEFAULT 0,
            is_hot TINYINT(1) NULL DEFAULT 0,
            latitude DECIMAL(10,8) NULL DEFAULT 0,
            longitude DECIMAL(11,8) NULL DEFAULT 0,
            slug VARCHAR(255) NULL,
            data_hash VARCHAR(32) NULL,
            city_id INT NULL DEFAULT 1,
            neighborhood_id INT NULL DEFAULT 1,
            property_type_id INT NULL DEFAULT 1,
            property_use_id INT NULL DEFAULT 1,
            property_state_id INT NULL DEFAULT 1,
            consignment_type_id INT NULL DEFAULT 1,
            advisor_id INT NULL DEFAULT 1,
            uso_id INT NULL DEFAULT 1,
            estado_actual_id INT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (property_state_id) REFERENCES property_states(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Tabla 'properties' creada correctamente\n";
}

// La tabla property_states ya fue creada anteriormente

// 3. Tabla images
$imagesExists = $connection->query("SHOW TABLES LIKE 'images'")->rowCount() > 0;
if (!$imagesExists) {
    echo "Creando tabla 'images'...\n";
    $connection->exec("
        CREATE TABLE images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            property_id INT NOT NULL,
            url VARCHAR(255) NOT NULL,
            local_url VARCHAR(255) NULL,
            order_num INT DEFAULT 0,
            is_downloaded TINYINT(1) DEFAULT 0,
            is_featured TINYINT(1) DEFAULT 0,
            laravel_disk VARCHAR(50) NULL,
            laravel_path VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (property_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Tabla 'images' creada correctamente\n";
}

// 4. Tabla cities
$citiesExists = $connection->query("SHOW TABLES LIKE 'cities'")->rowCount() > 0;
if (!$citiesExists) {
    echo "Creando tabla 'cities'...\n";
    $connection->exec("
        CREATE TABLE cities (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            state VARCHAR(100) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Insertar ciudades predeterminadas
    $connection->exec("
        INSERT INTO cities (name, state) VALUES 
        ('Barranquilla', 'Atlántico'),
        ('Bogotá', 'Cundinamarca'),
        ('Medellín', 'Antioquia'),
        ('Cali', 'Valle del Cauca'),
        ('Cartagena', 'Bolívar'),
        ('Santa Marta', 'Magdalena'),
        ('Sincelejo', 'Sucre')
    ");
    
    echo "Tabla 'cities' creada correctamente\n";
}

// 5. Tabla neighborhoods
$neighborhoodsExists = $connection->query("SHOW TABLES LIKE 'neighborhoods'")->rowCount() > 0;
if (!$neighborhoodsExists) {
    echo "Creando tabla 'neighborhoods'...\n";
    $connection->exec("
        CREATE TABLE neighborhoods (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            city_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (city_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Insertar barrios predeterminados
    $connection->exec("
        INSERT INTO neighborhoods (name, city_id) VALUES 
        ('SIN DEFINIR', 1),
        ('CENTRO', 1),
        ('NORTE', 1),
        ('SUR', 1),
        ('ESTE', 1),
        ('OESTE', 1)
    ");
    
    echo "Tabla 'neighborhoods' creada correctamente\n";
}

// 6. Tabla property_types
$propertyTypesExists = $connection->query("SHOW TABLES LIKE 'property_types'")->rowCount() > 0;
if (!$propertyTypesExists) {
    echo "Creando tabla 'property_types'...\n";
    $connection->exec("
        CREATE TABLE property_types (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Insertar tipos de propiedades predeterminados
    $connection->exec("
        INSERT INTO property_types (name) VALUES 
        ('Apartamento'),
        ('Casa'),
        ('Local'),
        ('Oficina'),
        ('Bodega'),
        ('Lote'),
        ('Finca'),
        ('Casa Campestre')
    ");
    
    echo "Tabla 'property_types' creada correctamente\n";
}

// 7. Tabla property_uses
$propertyUsesExists = $connection->query("SHOW TABLES LIKE 'property_uses'")->rowCount() > 0;
if (!$propertyUsesExists) {
    echo "Creando tabla 'property_uses'...\n";
    $connection->exec("
        CREATE TABLE property_uses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Insertar usos de propiedades predeterminados
    $connection->exec("
        INSERT INTO property_uses (name) VALUES 
        ('Vivienda'),
        ('Comercial'),
        ('Industrial'),
        ('Mixto')
    ");
    
    echo "Tabla 'property_uses' creada correctamente\n";
}

// 8. Tabla consignment_types
$consignmentTypesExists = $connection->query("SHOW TABLES LIKE 'consignment_types'")->rowCount() > 0;
if (!$consignmentTypesExists) {
    echo "Creando tabla 'consignment_types'...\n";
    $connection->exec("
        CREATE TABLE consignment_types (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Insertar tipos de consignación predeterminados
    $connection->exec("
        INSERT INTO consignment_types (name) VALUES 
        ('Venta'),
        ('Arriendo'),
        ('Mixto')
    ");
    
    echo "Tabla 'consignment_types' creada correctamente\n";
}

// 9. Tabla characteristics (características)
$characteristicsExists = $connection->query("SHOW TABLES LIKE 'characteristics'")->rowCount() > 0;
if (!$characteristicsExists) {
    echo "Creando tabla 'characteristics'...\n";
    $connection->exec("
        CREATE TABLE characteristics (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "Tabla 'characteristics' creada correctamente\n";
}

// 10. Tabla property_characteristics (relación entre propiedades y características)
$propertyCharacteristicsExists = $connection->query("SHOW TABLES LIKE 'property_characteristics'")->rowCount() > 0;
if (!$propertyCharacteristicsExists) {
    echo "Creando tabla 'property_characteristics'...\n";
    $connection->exec("
        CREATE TABLE property_characteristics (
            id INT AUTO_INCREMENT PRIMARY KEY,
            property_id INT NOT NULL,
            characteristic_id INT NOT NULL,
            value TEXT NULL,
            is_numeric TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (property_id),
            INDEX (characteristic_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Tabla 'property_characteristics' creada correctamente\n";
}

// 11. Tabla excluded_properties
$excludedPropertiesExists = $connection->query("SHOW TABLES LIKE 'excluded_properties'")->rowCount() > 0;
if (!$excludedPropertiesExists) {
    echo "Creando tabla 'excluded_properties'...\n";
    $connection->exec("
        CREATE TABLE excluded_properties (
            id INT AUTO_INCREMENT PRIMARY KEY,
            identifier VARCHAR(50) NOT NULL,
            identifier_type ENUM('id', 'ref', 'sync_code') NOT NULL DEFAULT 'ref',
            reason VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY (identifier, identifier_type),
            INDEX (identifier),
            INDEX (identifier_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Tabla 'excluded_properties' creada correctamente\n";
}

echo "Todas las tablas necesarias existen\n\n";

// Verificar carpeta de imágenes

echo "Verificando carpeta de imágenes...\n";
if (!is_dir($imagesFolder)) {
    echo "Creando carpeta de imágenes: {$imagesFolder}\n";
    mkdir($imagesFolder, 0755, true);
}

if (!is_dir($imagesFolder)) {
    echo "Error: No se pudo crear la carpeta de imágenes\n";
    exit(1);
}

echo "Carpeta de imágenes configurada correctamente: {$imagesFolder}\n\n";

// Verificar si se debe omitir la sincronización
if (isset($options['no-sync'])) {
    echo "Opción --no-sync especificada. No se sincronizarán datos.\n";
    echo "Las tablas han sido creadas correctamente.\n";
    exit(0);
}

// Obtener datos de la API
$apiUrl = getenv('API_URL');
echo "Obteniendo datos de la API: {$apiUrl}\n";

$response = file_get_contents($apiUrl);
$data = json_decode($response, true);

// Verificar estructura de la respuesta
if (isset($data['data']) && is_array($data['data'])) {
    $properties = $data['data'];
    echo "Se encontraron " . count($properties) . " propiedades en la respuesta\n";
    
    // Filtrar por propiedad específica si se solicita
    if (isset($options['property'])) {
        $propertyRef = $options['property'];
        echo "Filtrando solo la propiedad con referencia: {$propertyRef}\n";
        
        $filteredProperties = [];
        foreach ($properties as $property) {
            if ($property['ref'] == $propertyRef) {
                $filteredProperties[] = $property;
                break;
            }
        }
        
        if (empty($filteredProperties)) {
            echo "Error: No se encontró la propiedad con referencia {$propertyRef} en la API\n";
            exit(1);
        }
        
        $properties = $filteredProperties;
    }
    
    // Limitar el número de propiedades si se solicita
    if (isset($options['limit'])) {
        $limit = intval($options['limit']);
        echo "Limitando a {$limit} propiedades de " . count($properties) . " disponibles\n";
        $properties = array_slice($properties, 0, $limit);
    }
    
    $propertiesToProcess = count($properties);
    echo "Se procesarán {$propertiesToProcess} propiedades\n\n";
    
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
    
    // Obtener lista de propiedades excluidas
    $excludedProperties = [
        'id' => [],
        'ref' => [],
        'sync_code' => []
    ];
    
    $excludedStmt = $connection->query("SELECT identifier, identifier_type, reason FROM excluded_properties");
    while ($row = $excludedStmt->fetch(PDO::FETCH_ASSOC)) {
        $excludedProperties[$row['identifier_type']][$row['identifier']] = $row['reason'];
    }
    
    $totalExcluded = count($excludedProperties['id']) + count($excludedProperties['ref']) + count($excludedProperties['sync_code']);
    if ($totalExcluded > 0) {
        echo "Se encontraron {$totalExcluded} propiedades excluidas de la sincronización\n";
    }
    
    // Procesar propiedades
    $propertyProcessor = new PropertyProcessor(
        $connection,
        $imagesFolder,
        true, // Descargar imágenes
        true, // Registrar cambios
        $stats
    );
    
    // Eliminar propiedades excluidas que existen en la base de datos
    // 1. Primero, procesar exclusiones por ID
    foreach ($excludedProperties['id'] as $excludedId => $reason) {
        // Verificar si la propiedad existe en la base de datos
        $stmt = $connection->prepare("SELECT id, ref FROM properties WHERE id = ?");
        $stmt->execute([$excludedId]);
        $property = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($property) {
            $propertyId = $property['id'];
            $propertyRef = $property['ref'];
            echo "\nEliminando propiedad excluida con ID #{$excludedId} (Ref: {$propertyRef})\n";
            echo "Razón de exclusión: " . ($reason ?: 'No especificada') . "\n";
            
            // Eliminar imágenes físicas
            echo "Eliminando imágenes físicas...\n";
            $stmt = $connection->prepare("SELECT local_url FROM images WHERE property_id = ?");
            $stmt->execute([$propertyId]);
            $images = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $deletedImages = 0;
            foreach ($images as $imagePath) {
                if (!empty($imagePath) && file_exists($imagePath)) {
                    unlink($imagePath);
                    $deletedImages++;
                }
            }
            
            // Eliminar directorios vacíos
            $propertyDir = $imagesFolder . '/property_' . $excludedRef;
            $propertyDirAlt = $imagesFolder . '/' . $excludedRef;
            
            if (is_dir($propertyDir)) {
                $files = scandir($propertyDir);
                $isEmpty = count($files) <= 2; // Solo . y ..
                
                if ($isEmpty) {
                    rmdir($propertyDir);
                    echo "Directorio vacío eliminado: $propertyDir\n";
                }
            }
            
            if (is_dir($propertyDirAlt)) {
                $files = scandir($propertyDirAlt);
                $isEmpty = count($files) <= 2; // Solo . y ..
                
                if ($isEmpty) {
                    rmdir($propertyDirAlt);
                    echo "Directorio vacío eliminado: $propertyDirAlt\n";
                }
            }
            
            // Eliminar registros de la base de datos
            echo "Eliminando registros de la base de datos...\n";
            
            // Eliminar características
            $stmt = $connection->prepare("DELETE FROM property_characteristics WHERE property_id = ?");
            $stmt->execute([$propertyId]);
            $deletedCharacteristics = $stmt->rowCount();
            
            // Eliminar imágenes
            $stmt = $connection->prepare("DELETE FROM images WHERE property_id = ?");
            $stmt->execute([$propertyId]);
            $deletedImageRecords = $stmt->rowCount();
            
            // Eliminar propiedad
            $stmt = $connection->prepare("DELETE FROM properties WHERE id = ?");
            $stmt->execute([$propertyId]);
            
            echo "Propiedad excluida eliminada con éxito:\n";
            echo "- Imágenes físicas eliminadas: $deletedImages\n";
            echo "- Registros de imágenes eliminados: $deletedImageRecords\n";
            echo "- Características eliminadas: $deletedCharacteristics\n";
            
            // Actualizar estadísticas
            $stats['inmuebles_procesados']++;
        }
    }
    
    // 2. Procesar exclusiones por referencia (ref)
    foreach ($excludedProperties['ref'] as $excludedRef => $reason) {
        // Verificar si la propiedad existe en la base de datos
        $stmt = $connection->prepare("SELECT id, ref FROM properties WHERE ref = ?");
        $stmt->execute([$excludedRef]);
        $property = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($property) {
            $propertyId = $property['id'];
            $propertyRef = $property['ref'];
            echo "\nEliminando propiedad excluida con Ref #{$excludedRef} (ID: {$propertyId})\n";
            echo "Razón de exclusión: " . ($reason ?: 'No especificada') . "\n";
            
            // Eliminar imágenes físicas
            echo "Eliminando imágenes físicas...\n";
            $stmt = $connection->prepare("SELECT local_url FROM images WHERE property_id = ?");
            $stmt->execute([$propertyId]);
            $images = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $deletedImages = 0;
            foreach ($images as $imagePath) {
                if (!empty($imagePath) && file_exists($imagePath)) {
                    unlink($imagePath);
                    $deletedImages++;
                }
            }
            
            // Eliminar directorios vacíos
            $propertyDir = $imagesFolder . '/property_' . $propertyRef;
            $propertyDirAlt = $imagesFolder . '/' . $propertyRef;
            
            if (is_dir($propertyDir)) {
                $files = scandir($propertyDir);
                $isEmpty = count($files) <= 2; // Solo . y ..
                
                if ($isEmpty) {
                    rmdir($propertyDir);
                    echo "Directorio vacío eliminado: $propertyDir\n";
                }
            }
            
            if (is_dir($propertyDirAlt)) {
                $files = scandir($propertyDirAlt);
                $isEmpty = count($files) <= 2; // Solo . y ..
                
                if ($isEmpty) {
                    rmdir($propertyDirAlt);
                    echo "Directorio vacío eliminado: $propertyDirAlt\n";
                }
            }
            
            // Eliminar registros de la base de datos
            echo "Eliminando registros de la base de datos...\n";
            
            // Eliminar características
            $stmt = $connection->prepare("DELETE FROM property_characteristics WHERE property_id = ?");
            $stmt->execute([$propertyId]);
            $deletedCharacteristics = $stmt->rowCount();
            
            // Eliminar imágenes
            $stmt = $connection->prepare("DELETE FROM images WHERE property_id = ?");
            $stmt->execute([$propertyId]);
            $deletedImageRecords = $stmt->rowCount();
            
            // Eliminar propiedad
            $stmt = $connection->prepare("DELETE FROM properties WHERE id = ?");
            $stmt->execute([$propertyId]);
            
            echo "Propiedad excluida eliminada con éxito:\n";
            echo "- Imágenes físicas eliminadas: $deletedImages\n";
            echo "- Registros de imágenes eliminados: $deletedImageRecords\n";
            echo "- Características eliminadas: $deletedCharacteristics\n";
            
            // Actualizar estadísticas
            $stats['inmuebles_procesados']++;
        }
    }
    
    // 3. Procesar exclusiones por código de sincronización (sync_code)
    foreach ($excludedProperties['sync_code'] as $excludedSyncCode => $reason) {
        // Verificar si la propiedad existe en la base de datos
        $stmt = $connection->prepare("SELECT id, ref FROM properties WHERE sync_code = ?");
        $stmt->execute([$excludedSyncCode]);
        $property = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($property) {
            $propertyId = $property['id'];
            $propertyRef = $property['ref'];
            echo "\nEliminando propiedad excluida con Sync Code #{$excludedSyncCode} (ID: {$propertyId}, Ref: {$propertyRef})\n";
            echo "Razón de exclusión: " . ($reason ?: 'No especificada') . "\n";
            
            // Eliminar imágenes físicas
            echo "Eliminando imágenes físicas...\n";
            $stmt = $connection->prepare("SELECT local_url FROM images WHERE property_id = ?");
            $stmt->execute([$propertyId]);
            $images = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $deletedImages = 0;
            foreach ($images as $imagePath) {
                if (!empty($imagePath) && file_exists($imagePath)) {
                    unlink($imagePath);
                    $deletedImages++;
                }
            }
            
            // Eliminar directorios vacíos
            $propertyDir = $imagesFolder . '/property_' . $propertyRef;
            $propertyDirAlt = $imagesFolder . '/' . $propertyRef;
            
            if (is_dir($propertyDir)) {
                $files = scandir($propertyDir);
                $isEmpty = count($files) <= 2; // Solo . y ..
                
                if ($isEmpty) {
                    rmdir($propertyDir);
                    echo "Directorio vacío eliminado: $propertyDir\n";
                }
            }
            
            if (is_dir($propertyDirAlt)) {
                $files = scandir($propertyDirAlt);
                $isEmpty = count($files) <= 2; // Solo . y ..
                
                if ($isEmpty) {
                    rmdir($propertyDirAlt);
                    echo "Directorio vacío eliminado: $propertyDirAlt\n";
                }
            }
            
            // Eliminar registros de la base de datos
            echo "Eliminando registros de la base de datos...\n";
            
            // Eliminar características
            $stmt = $connection->prepare("DELETE FROM property_characteristics WHERE property_id = ?");
            $stmt->execute([$propertyId]);
            $deletedCharacteristics = $stmt->rowCount();
            
            // Eliminar imágenes
            $stmt = $connection->prepare("DELETE FROM images WHERE property_id = ?");
            $stmt->execute([$propertyId]);
            $deletedImageRecords = $stmt->rowCount();
            
            // Eliminar propiedad
            $stmt = $connection->prepare("DELETE FROM properties WHERE id = ?");
            $stmt->execute([$propertyId]);
            
            echo "Propiedad excluida eliminada con éxito:\n";
            echo "- Imágenes físicas eliminadas: $deletedImages\n";
            echo "- Registros de imágenes eliminados: $deletedImageRecords\n";
            echo "- Características eliminadas: $deletedCharacteristics\n";
            
            // Actualizar estadísticas
            $stats['inmuebles_procesados']++;
        }
    }
    
    $count = 0;
    foreach ($properties as $property) {
        $count++;
        $percentage = round(($count / $propertiesToProcess) * 100);
        
        $ref = isset($property['ref']) ? $property['ref'] : '';
        $id = isset($property['id']) ? $property['id'] : '';
        $syncCode = isset($property['codigo_sincronizacion']) ? $property['codigo_sincronizacion'] : '';
        
        // Verificar si la propiedad está excluida por alguno de sus identificadores
        $isExcluded = false;
        $exclusionReason = '';
        
        // Verificar por ID
        if ($id && isset($excludedProperties['id'][$id])) {
            $isExcluded = true;
            $exclusionReason = $excludedProperties['id'][$id] ?: 'No especificada';
        }
        // Verificar por referencia
        elseif ($ref && isset($excludedProperties['ref'][$ref])) {
            $isExcluded = true;
            $exclusionReason = $excludedProperties['ref'][$ref] ?: 'No especificada';
        }
        // Verificar por código de sincronización
        elseif ($syncCode && isset($excludedProperties['sync_code'][$syncCode])) {
            $isExcluded = true;
            $exclusionReason = $excludedProperties['sync_code'][$syncCode] ?: 'No especificada';
        }
        
        if ($isExcluded) {
            echo "\nInmueble #{$count}/{$propertiesToProcess} ({$percentage}%): Ref {$ref} - EXCLUIDO DE LA SINCRONIZACIÓN\n";
            echo "Razón: {$exclusionReason}\n";
            continue; // Saltar esta propiedad
        }
        
        echo "\nProcesando inmueble #{$count}/{$propertiesToProcess} ({$percentage}%): Ref {$ref}\n";
        
        try {
            // Normalizar datos de la API al formato de la base de datos
            // (Este paso ya lo hace internamente el PropertyProcessor)
            
            // Procesar la propiedad
            $propertyId = $propertyProcessor->processProperty($property);
            
            // Incrementar contador de propiedades procesadas
            $stats['inmuebles_procesados']++;
        } catch (Exception $e) {
            echo "Error al procesar propiedad: " . $e->getMessage() . "\n";
            $stats['errores']++;
        }
    }
    
    // Mostrar resumen
    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);
    
    echo "\n======================================================================\n";
    echo "RESUMEN DE SINCRONIZACIÓN\n";
    echo "======================================================================\n";
    echo "Inicio: " . date('Y-m-d H:i:s', intval($startTime)) . "\n";
    echo "Fin: " . date('Y-m-d H:i:s', intval($endTime)) . "\n";
    echo "Duración: {$duration} segundos\n\n";
    
    echo "Inmuebles procesados: " . $stats['inmuebles_procesados'] . "\n";
    echo "Inmuebles nuevos: " . $stats['inmuebles_nuevos'] . "\n";
    echo "Inmuebles actualizados: " . $stats['inmuebles_actualizados'] . "\n";
    echo "Inmuebles sin cambios: " . $stats['inmuebles_sin_cambios'] . "\n";
    echo "Imágenes descargadas: " . $stats['imagenes_descargadas'] . "\n";
    echo "Imágenes eliminadas: " . $stats['imagenes_eliminadas'] . "\n";
    echo "Errores: " . $stats['errores'] . "\n";
    echo "======================================================================\n\n";
    
    echo "Sincronización completada en {$duration} segundos\n";
    echo "Resultados:\n";
    echo "  Inmuebles procesados: " . $stats['inmuebles_procesados'] . "\n";
    echo "  Inmuebles nuevos: " . $stats['inmuebles_nuevos'] . "\n";
    echo "  Inmuebles actualizados: " . $stats['inmuebles_actualizados'] . "\n";
    echo "  Inmuebles sin cambios: " . $stats['inmuebles_sin_cambios'] . "\n";
    echo "  Imágenes descargadas: " . $stats['imagenes_descargadas'] . "\n";
    echo "  Imágenes eliminadas: " . $stats['imagenes_eliminadas'] . "\n";
    echo "  Errores: " . $stats['errores'] . "\n";
} else {
    echo "Error: Estructura de respuesta no reconocida\n";
    exit(1);
}
