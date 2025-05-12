<?php
/**
 * Script para reiniciar completamente la base de datos usando convenciones de Laravel
 * 
 * Este script elimina todas las tablas y las vuelve a crear desde cero
 * siguiendo las convenciones de Laravel (nombres en inglés).
 * ADVERTENCIA: Este script eliminará TODOS los datos existentes.
 * 
 * Uso: php commands/reset-laravel.php [--confirm]
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Database.php';

// Verificar confirmación
$options = getopt('', ['confirm']);
$confirmed = isset($options['confirm']);

if (!$confirmed) {
    echo "ADVERTENCIA: Este script eliminará TODOS los datos de la base de datos.\n";
    echo "Para confirmar, ejecute el script con la opción --confirm\n";
    echo "Ejemplo: php commands/reset-laravel.php --confirm\n";
    exit(1);
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

// Desactivar restricciones de clave foránea
try {
    $connection->query("SET FOREIGN_KEY_CHECKS = 0");
    echo "Restricciones de clave foránea desactivadas\n";
} catch (Exception $e) {
    echo "Error al desactivar restricciones: " . $e->getMessage() . "\n";
}

// Eliminar tablas existentes (tanto en español como en inglés)
$tables = [
    // Tablas con nombres en inglés (Laravel)
    'images',
    'properties',
    'property_changes',
    'property_sync_statuses',
    'characteristic_property',
    'characteristics',
    'consignment_types',
    'property_statuses',
    'property_uses',
    'property_types',
    'neighborhoods',
    'cities',
    'advisors',
    'configurations',
    'sync_logs',
    'property_characteristics',
    'property_characteristic_values',
    
    // Tablas con nombres en español (por si acaso)
    'imagenes',
    'inmuebles',
    'cambios_inmuebles',
    'inmuebles_estado',
    'caracteristica_inmueble',
    'caracteristicas',
    'tipo_consignacion',
    'estados_inmueble',
    'usos_inmueble',
    'tipos_inmueble',
    'barrios',
    'ciudades',
    'asesores',
    'configuraciones',
    'sincronizacion_logs',
    'inmueble_caracteristicas',
    'caracteristicas_inmueble'
];

foreach ($tables as $table) {
    try {
        $connection->query("DROP TABLE IF EXISTS {$table}");
        echo "Tabla {$table} eliminada correctamente\n";
    } catch (Exception $e) {
        echo "Error al eliminar la tabla {$table}: " . $e->getMessage() . "\n";
    }
}

// Reactivar restricciones de clave foránea
try {
    $connection->query("SET FOREIGN_KEY_CHECKS = 1");
    echo "Restricciones de clave foránea reactivadas\n";
} catch (Exception $e) {
    echo "Error al reactivar restricciones: " . $e->getMessage() . "\n";
}

// Borrar imágenes físicas y carpetas
echo "Borrando imágenes y carpetas...\n";

// Obtener la ruta de imágenes configurada
$imagesFolder = defined('IMAGES_FOLDER') ? IMAGES_FOLDER : __DIR__ . '/../public/images/inmuebles';

// Determinar el modo de almacenamiento
$storageMode = defined('IMAGES_STORAGE_MODE') ? IMAGES_STORAGE_MODE : 'local';

// Si estamos en modo Laravel, usar la ruta de Laravel
if ($storageMode === 'laravel') {
    $laravelStoragePath = getenv('LARAVEL_STORAGE_PATH') ?: '/var/www/html/storage/app/public';
    $laravelImagesPath = getenv('LARAVEL_IMAGES_PATH') ?: 'images/inmuebles';
    $imagesFolder = rtrim($laravelStoragePath, '/') . '/' . ltrim($laravelImagesPath, '/');
}

echo "Modo de almacenamiento: {$storageMode}\n";
echo "Directorio de imágenes configurado: {$imagesFolder}\n";

// Verificar si el directorio existe
if (is_dir($imagesFolder)) {
    // Enfoque 1: Eliminar y recrear el directorio completo
    echo "Eliminando el directorio completo y recreándolo...\n";
    
    // Eliminar el directorio y todo su contenido
    $command = "rm -rf " . escapeshellarg($imagesFolder);
    echo "Ejecutando: {$command}\n";
    system($command, $returnCode);
    
    if ($returnCode === 0) {
        echo "Directorio eliminado correctamente\n";
    } else {
        echo "Error al eliminar el directorio: código {$returnCode}\n";
    }
}

// Crear el directorio de imágenes
echo "Creando el directorio de imágenes...\n";
if (!is_dir($imagesFolder)) {
    if (mkdir($imagesFolder, 0755, true)) {
        echo "Directorio de imágenes creado correctamente\n";
    } else {
        echo "Error al crear el directorio de imágenes\n";
    }
}

// Verificar permisos
if ($storageMode === 'laravel') {
    echo "Verificando permisos en modo Laravel...\n";
    
    // Verificar que el directorio tenga los permisos correctos
    if (is_writable($imagesFolder)) {
        echo "El directorio tiene los permisos correctos\n";
    } else {
        echo "Advertencia: El directorio no tiene permisos de escritura\n";
        
        // Intentar corregir los permisos
        chmod($imagesFolder, 0755);
        if (is_writable($imagesFolder)) {
            echo "Permisos corregidos correctamente\n";
        } else {
            echo "Error: No se pudieron corregir los permisos\n";
        }
    }
}

// Verificar que el directorio esté vacío
$files = glob($imagesFolder . '/*');
if (count($files) === 0) {
    echo "El directorio está vacío\n";
} else {
    echo "Advertencia: Aún quedan " . count($files) . " elementos en el directorio\n";
}

// Crear nuevas tablas siguiendo las convenciones de Laravel
echo "Creando nuevas tablas...\n";

// Ejecutar el script SQL de inicialización
$sqlFile = __DIR__ . '/../docker/mysql/init/01-init-laravel.sql';

if (file_exists($sqlFile)) {
    $sql = file_get_contents($sqlFile);
    
    // Dividir el SQL en consultas individuales
    $queries = explode(';', $sql);
    
    // Ejecutar cada consulta
    foreach ($queries as $query) {
        $query = trim($query);
        if (!empty($query)) {
            try {
                $connection->query($query);
                
                // Extraer el nombre de la tabla de la consulta CREATE TABLE
                if (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?/i', $query, $matches)) {
                    $tableName = $matches[1];
                    echo "Tabla {$tableName} creada correctamente\n";
                }
            } catch (Exception $e) {
                echo "Error al ejecutar consulta SQL: " . $e->getMessage() . "\n";
                echo "Consulta: " . substr($query, 0, 100) . "...\n";
            }
        }
    }
} else {
    echo "Error: No se encontró el archivo SQL de inicialización: {$sqlFile}\n";
    
    // Crear tablas manualmente si no se encuentra el archivo SQL
    try {
        // Crear tabla advisors (antes asesores)
        $connection->query("
            CREATE TABLE advisors (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                last_name VARCHAR(255) DEFAULT NULL,
                email VARCHAR(255) DEFAULT NULL,
                phone VARCHAR(50) DEFAULT NULL,
                image VARCHAR(255) DEFAULT NULL,
                active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "Tabla advisors creada correctamente\n";
        
        // Insertar asesor por defecto
        $connection->query("INSERT INTO advisors (id, name, active) VALUES (1, 'Oficina', 1)");
        echo "Asesor por defecto creado correctamente\n";
        
        // Crear tabla cities (antes ciudades)
        $connection->query("
            CREATE TABLE cities (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "Tabla cities creada correctamente\n";
        
        // Insertar ciudad por defecto
        $connection->query("INSERT INTO cities (id, name) VALUES (1, 'Bogotá')");
        echo "Ciudad por defecto creada correctamente\n";
        
        // Crear tabla neighborhoods (antes barrios)
        $connection->query("
            CREATE TABLE neighborhoods (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                city_id INT DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY name (name),
                FOREIGN KEY (city_id) REFERENCES cities(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "Tabla neighborhoods creada correctamente\n";
        
        // Insertar barrio por defecto
        $connection->query("INSERT INTO neighborhoods (id, name) VALUES (1, 'Centro')");
        echo "Barrio por defecto creado correctamente\n";
        
        // Crear tabla property_types (antes tipos_inmueble)
        $connection->query("
            CREATE TABLE property_types (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "Tabla property_types creada correctamente\n";
        
        // Insertar tipos de inmueble por defecto
        $connection->query("
            INSERT INTO property_types (id, name) VALUES 
            (1, 'Apartamento'),
            (2, 'Casa'),
            (3, 'Local'),
            (4, 'Oficina'),
            (5, 'Bodega'),
            (6, 'Lote')
        ");
        echo "Tipos de inmueble por defecto creados correctamente\n";
        
        // Crear tabla property_uses (antes usos_inmueble)
        $connection->query("
            CREATE TABLE property_uses (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "Tabla property_uses creada correctamente\n";
        
        // Insertar usos de inmueble por defecto
        $connection->query("
            INSERT INTO property_uses (id, name) VALUES 
            (1, 'Vivienda'),
            (2, 'Comercial'),
            (3, 'Mixto'),
            (4, 'Industrial')
        ");
        echo "Usos de inmueble por defecto creados correctamente\n";
        
        // Crear tabla property_statuses (antes estados_inmueble)
        $connection->query("
            CREATE TABLE property_statuses (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "Tabla property_statuses creada correctamente\n";
        
        // Insertar estados de inmueble por defecto
        $connection->query("
            INSERT INTO property_statuses (id, name) VALUES 
            (1, 'Disponible'),
            (2, 'Arrendado'),
            (3, 'Vendido')
        ");
        echo "Estados de inmueble por defecto creados correctamente\n";
        
        // Crear tabla consignment_types (antes tipo_consignacion)
        $connection->query("
            CREATE TABLE consignment_types (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                description TEXT DEFAULT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "Tabla consignment_types creada correctamente\n";
        
        // Insertar tipos de consignación por defecto
        $connection->query("
            INSERT INTO consignment_types (id, name) VALUES 
            (1, 'Venta'),
            (2, 'Arriendo'),
            (3, 'Venta y Arriendo')
        ");
        echo "Tipos de consignación por defecto creados correctamente\n";
        
        // Crear tabla properties (antes inmuebles)
        $connection->query("
            CREATE TABLE properties (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ref VARCHAR(50) NOT NULL UNIQUE,
                sync_code VARCHAR(100),
                title VARCHAR(255) NOT NULL,
                description TEXT,
                short_description VARCHAR(255),
                address VARCHAR(255),
                sale_price DECIMAL(15,2) DEFAULT 0,
                rent_price DECIMAL(15,2) DEFAULT 0,
                administration_fee DECIMAL(15,2) DEFAULT 0,
                total_price DECIMAL(15,2) DEFAULT 0,
                built_area FLOAT DEFAULT 0,
                private_area FLOAT DEFAULT 0,
                total_area FLOAT DEFAULT 0,
                land_area FLOAT DEFAULT 0,
                bedrooms INT DEFAULT 0,
                bathrooms INT DEFAULT 0,
                garages INT DEFAULT 0,
                stratum INT DEFAULT 0,
                age INT DEFAULT 0,
                floor INT DEFAULT 0,
                has_elevator TINYINT(1) DEFAULT 0,
                is_featured TINYINT(1) DEFAULT 0,
                is_active TINYINT(1) DEFAULT 1,
                is_hot TINYINT(1) DEFAULT 0,
                latitude DECIMAL(10,8) DEFAULT 0,
                longitude DECIMAL(11,8) DEFAULT 0,
                slug VARCHAR(255),
                property_type_id INT DEFAULT 1,
                neighborhood_id INT DEFAULT 1,
                city_id INT DEFAULT 1,
                advisor_id INT DEFAULT 1,
                property_use_id INT DEFAULT 1,
                property_status_id INT DEFAULT 1,
                consignment_type_id INT DEFAULT 1,
                hash_datos VARCHAR(32),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (property_type_id) REFERENCES property_types(id),
                FOREIGN KEY (neighborhood_id) REFERENCES neighborhoods(id),
                FOREIGN KEY (city_id) REFERENCES cities(id),
                FOREIGN KEY (advisor_id) REFERENCES advisors(id),
                FOREIGN KEY (property_use_id) REFERENCES property_uses(id),
                FOREIGN KEY (property_status_id) REFERENCES property_statuses(id),
                FOREIGN KEY (consignment_type_id) REFERENCES consignment_types(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "Tabla properties creada correctamente\n";
        
        // Crear tabla images (antes imagenes)
        $connection->query("
            CREATE TABLE images (
                id INT AUTO_INCREMENT PRIMARY KEY,
                property_id INT NOT NULL,
                url VARCHAR(255) NOT NULL,
                local_url VARCHAR(255),
                disk VARCHAR(50) NULL,
                path VARCHAR(255) NULL,
                order_num INT DEFAULT 0,
                hash VARCHAR(32),
                is_downloaded TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX (property_id),
                FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "Tabla images creada correctamente\n";
        
        // Crear tabla property_changes (antes cambios_inmuebles)
        $connection->query("
            CREATE TABLE property_changes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                property_id INT NOT NULL,
                field VARCHAR(50) NOT NULL,
                old_value TEXT,
                new_value TEXT,
                changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX (property_id),
                FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "Tabla property_changes creada correctamente\n";
        
        // Crear tabla property_sync_statuses (antes inmuebles_estado)
        $connection->query("
            CREATE TABLE property_sync_statuses (
                id INT AUTO_INCREMENT PRIMARY KEY,
                property_ref VARCHAR(50) NOT NULL,
                sync_code VARCHAR(100),
                is_active TINYINT(1) DEFAULT 1,
                last_sync TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY property_ref (property_ref)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "Tabla property_sync_statuses creada correctamente\n";
        
        // Crear tabla characteristics (antes caracteristicas)
        $connection->query("
            CREATE TABLE characteristics (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "Tabla characteristics creada correctamente\n";
        
        // Crear tabla characteristic_property (antes caracteristica_inmueble)
        $connection->query("
            CREATE TABLE characteristic_property (
                id INT AUTO_INCREMENT PRIMARY KEY,
                property_id INT NOT NULL,
                characteristic_id INT NOT NULL,
                valor TEXT,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
                FOREIGN KEY (characteristic_id) REFERENCES characteristics(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "Tabla characteristic_property creada correctamente\n";
        
        // Crear tabla configurations
        $connection->query("
            CREATE TABLE configurations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                key VARCHAR(100) NOT NULL,
                value TEXT,
                description VARCHAR(255),
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY key_unique (key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "Tabla configurations creada correctamente\n";
        
        // Insertar configuraciones por defecto
        $connection->query("
            INSERT INTO configurations (key, value, description) VALUES 
            ('last_sync', NULL, 'Fecha y hora de la última sincronización'),
            ('sync_interval', '3600', 'Intervalo de sincronización en segundos (por defecto 1 hora)'),
            ('api_url', 'https://api.orbisaho.com/api/v1/inmuebles', 'URL de la API de Orbis AHO'),
            ('api_key', '', 'Clave de la API de Orbis AHO'),
            ('storage_path', '/var/www/html/storage/app/public/inmuebles', 'Ruta de almacenamiento de imágenes'),
            ('public_url', '/storage/inmuebles', 'URL pública para las imágenes')
        ");
        echo "Configuraciones por defecto creadas correctamente\n";
        
        // Crear tabla sync_logs
        $connection->query("
            CREATE TABLE sync_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                start_time TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                end_time TIMESTAMP NULL DEFAULT NULL,
                properties_processed INT DEFAULT 0,
                properties_created INT DEFAULT 0,
                properties_updated INT DEFAULT 0,
                properties_deactivated INT DEFAULT 0,
                images_downloaded INT DEFAULT 0,
                status VARCHAR(50) DEFAULT 'running',
                error_message TEXT,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "Tabla sync_logs creada correctamente\n";
        
    } catch (Exception $e) {
        echo "Error al crear tablas: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "\nReinicio de la base de datos completado correctamente.\n";
echo "Puede ejecutar 'php sync.php' para iniciar una nueva sincronización.\n";
