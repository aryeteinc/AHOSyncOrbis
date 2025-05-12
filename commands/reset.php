<?php
/**
 * Script para reiniciar completamente la base de datos
 * 
 * Este script elimina todas las tablas y las vuelve a crear desde cero.
 * ADVERTENCIA: Este script eliminará TODOS los datos existentes.
 * 
 * Uso: php commands/reset.php [--confirm]
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Database.php';

// Verificar confirmación
$options = getopt('', ['confirm']);
$confirmed = isset($options['confirm']);

if (!$confirmed) {
    echo "ADVERTENCIA: Este script eliminará TODOS los datos de la base de datos.\n";
    echo "Para confirmar, ejecute el script con la opción --confirm\n";
    echo "Ejemplo: php commands/reset.php --confirm\n";
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

// Eliminar tablas existentes
$tables = [
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
    'asesores'
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
} else {
    echo "El directorio no existe, no es necesario eliminarlo\n";
}

// Crear el directorio nuevamente
echo "Creando el directorio de imágenes...\n";
if (!is_dir($imagesFolder)) {
    if (mkdir($imagesFolder, 0755, true)) {
        echo "Directorio de imágenes creado correctamente\n";
        
        // Establecer permisos adecuados
        chmod($imagesFolder, 0755);
    } else {
        echo "Error: No se pudo crear el directorio de imágenes\n";
        echo "Intentando con el comando mkdir...\n";
        
        $command = "mkdir -p " . escapeshellarg($imagesFolder);
        system($command, $returnCode);
        
        if ($returnCode === 0) {
            echo "Directorio creado correctamente con el comando mkdir\n";
            chmod($imagesFolder, 0755);
        } else {
            echo "Error al crear el directorio: código {$returnCode}\n";
        }
    }
} else {
    echo "El directorio ya existe\n";
}

// Si estamos en modo Laravel, verificar que el directorio sea accesible
if ($storageMode === 'laravel') {
    echo "Verificando permisos en modo Laravel...\n";
    if (!is_writable($imagesFolder)) {
        echo "Advertencia: El directorio no tiene permisos de escritura\n";
        echo "Ajustando permisos...\n";
        chmod($imagesFolder, 0755);
    } else {
        echo "El directorio tiene los permisos correctos\n";
    }
}

// Verificar que el directorio esté vacío
$files = glob($imagesFolder . '/*');
if (count($files) === 0) {
    echo "El directorio está vacío\n";
} else {
    echo "Advertencia: Aún quedan " . count($files) . " elementos en el directorio\n";
    
    // Intentar un segundo enfoque si el primero falló
    echo "Intentando un segundo enfoque para limpiar el directorio...\n";
    $command = "find " . escapeshellarg($imagesFolder) . " -mindepth 1 -delete";
    echo "Ejecutando: {$command}\n";
    system($command, $returnCode);
    
    if ($returnCode === 0) {
        echo "Directorio limpiado correctamente con find\n";
    } else {
        echo "Error al limpiar el directorio con find: código {$returnCode}\n";
    }
    
    // Verificar nuevamente
    $files = glob($imagesFolder . '/*');
    if (count($files) === 0) {
        echo "El directorio ahora está vacío\n";
    } else {
        echo "Advertencia: Aún quedan " . count($files) . " elementos en el directorio\n";
    }
}

echo "Creando nuevas tablas...\n";

// Crear tabla de inmuebles
try {
    $connection->query("
        CREATE TABLE inmuebles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ref VARCHAR(50) NOT NULL UNIQUE,
            codigo_sincronizacion VARCHAR(100),
            titulo VARCHAR(255) NOT NULL,
            descripcion TEXT,
            descripcion_corta VARCHAR(255),
            direccion VARCHAR(255),
            precio_venta DECIMAL(15,2) DEFAULT 0,
            precio_arriendo DECIMAL(15,2) DEFAULT 0,
            administracion DECIMAL(15,2) DEFAULT 0,
            precio_total DECIMAL(15,2) DEFAULT 0,
            area_construida FLOAT DEFAULT 0,
            area_privada FLOAT DEFAULT 0,
            area_total FLOAT DEFAULT 0,
            area_terreno FLOAT DEFAULT 0,
            habitaciones INT DEFAULT 0,
            banos INT DEFAULT 0,
            garajes INT DEFAULT 0,
            estrato INT DEFAULT 0,
            antiguedad INT DEFAULT 0,
            piso INT DEFAULT 0,
            ascensor TINYINT(1) DEFAULT 0,
            destacado TINYINT(1) DEFAULT 0,
            activo TINYINT(1) DEFAULT 1,
            en_caliente TINYINT(1) DEFAULT 0,
            latitud DECIMAL(10,8) DEFAULT 0,
            longitud DECIMAL(11,8) DEFAULT 0,
            slug VARCHAR(255),
            ciudad_id INT DEFAULT 1,
            barrio_id INT DEFAULT 1,
            tipo_inmueble_id INT DEFAULT 1,
            uso_inmueble_id INT DEFAULT 1,
            uso_id INT DEFAULT 1,
            estado_inmueble_id INT DEFAULT 1,
            estado_actual_id INT DEFAULT 1,
            tipo_consignacion_id INT DEFAULT 1,
            asesor_id INT DEFAULT 1,
            hash_datos VARCHAR(32),
            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (ref),
            INDEX (slug),
            INDEX (ciudad_id),
            INDEX (barrio_id),
            INDEX (tipo_inmueble_id),
            INDEX (uso_inmueble_id),
            INDEX (estado_inmueble_id),
            INDEX (activo),
            INDEX (destacado)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Tabla inmuebles creada correctamente\n";
} catch (Exception $e) {
    echo "Error al crear la tabla inmuebles: " . $e->getMessage() . "\n";
    exit(1);
}

// Crear tabla de imágenes con soporte para Laravel
try {
    $connection->query("
        CREATE TABLE imagenes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            inmueble_id INT NOT NULL,
            url VARCHAR(255) NOT NULL,
            url_local VARCHAR(255),
            laravel_disk VARCHAR(50) NULL,
            laravel_path VARCHAR(255) NULL,
            orden INT DEFAULT 0,
            hash VARCHAR(32),
            descargada TINYINT(1) DEFAULT 1,
            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (inmueble_id),
            FOREIGN KEY (inmueble_id) REFERENCES inmuebles(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Tabla imagenes creada correctamente\n";
} catch (Exception $e) {
    echo "Error al crear la tabla imagenes: " . $e->getMessage() . "\n";
    exit(1);
}

// Crear tabla de cambios
try {
    $connection->query("
        CREATE TABLE cambios_inmuebles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            inmueble_id INT NOT NULL,
            campo VARCHAR(50) NOT NULL,
            valor_anterior TEXT,
            valor_nuevo TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (inmueble_id),
            FOREIGN KEY (inmueble_id) REFERENCES inmuebles(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Tabla cambios_inmuebles creada correctamente\n";
} catch (Exception $e) {
    echo "Error al crear la tabla cambios_inmuebles: " . $e->getMessage() . "\n";
    exit(1);
}

// Crear tabla de estados personalizados de inmuebles
try {
    $connection->query("
        CREATE TABLE inmuebles_estado (
            id INT AUTO_INCREMENT PRIMARY KEY,
            inmueble_ref VARCHAR(50) NOT NULL,
            codigo_sincronizacion VARCHAR(100),
            activo TINYINT(1) DEFAULT 1,
            destacado TINYINT(1) DEFAULT 0,
            en_caliente TINYINT(1) DEFAULT 0,
            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (inmueble_ref),
            INDEX (codigo_sincronizacion),
            INDEX (activo),
            INDEX (destacado),
            UNIQUE KEY (inmueble_ref, codigo_sincronizacion)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Tabla inmuebles_estado creada correctamente\n";
} catch (Exception $e) {
    echo "Error al crear la tabla inmuebles_estado: " . $e->getMessage() . "\n";
    exit(1);
}

// Crear tablas de catálogos
try {
    // Crear tabla asesores
    $connection->query("
        CREATE TABLE asesores (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(255) NOT NULL,
            apellido VARCHAR(255),
            email VARCHAR(255),
            telefono VARCHAR(50),
            imagen VARCHAR(255),
            activo TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Tabla asesores creada correctamente\n";
    
    // Insertar asesor por defecto
    $connection->query("
        INSERT INTO asesores (id, nombre, activo) VALUES (1, 'Oficina', 1)
    ");
    echo "Asesor por defecto creado correctamente\n";
    
    // Crear tabla ciudades
    $connection->query("
        CREATE TABLE ciudades (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (nombre)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Tabla ciudades creada correctamente\n";
    
    // No insertar ciudades predeterminadas, se crearán automáticamente durante la sincronización
    echo "Tabla ciudades creada sin datos predeterminados\n";
    
    // Crear tabla barrios
    $connection->query("
        CREATE TABLE barrios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(255) NOT NULL,
            ciudad_id INT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (nombre),
            INDEX (ciudad_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Tabla barrios creada correctamente\n";
    
    // No insertar barrios predeterminados, se crearán automáticamente durante la sincronización
    echo "Tabla barrios creada sin datos predeterminados\n";
    
    // Crear tabla tipos_inmueble
    $connection->query("
        CREATE TABLE tipos_inmueble (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY (nombre)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Tabla tipos_inmueble creada correctamente\n";
    
    // No insertar tipos de inmueble predeterminados, se crearán automáticamente durante la sincronización
    echo "Tabla tipos_inmueble creada sin datos predeterminados\n";
    
    // Crear tabla usos_inmueble
    $connection->query("
        CREATE TABLE usos_inmueble (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY (nombre)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Tabla usos_inmueble creada correctamente\n";
    
    // No insertar usos de inmueble predeterminados, se crearán automáticamente durante la sincronización
    echo "Tabla usos_inmueble creada sin datos predeterminados\n";
    
    // Crear tabla estados_inmueble
    $connection->query("
        CREATE TABLE estados_inmueble (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY (nombre)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Tabla estados_inmueble creada correctamente\n";
    
    // No insertar estados de inmueble predeterminados, se crearán automáticamente durante la sincronización
    echo "Tabla estados_inmueble creada sin datos predeterminados\n";
    
    // Crear tabla tipo_consignacion
    $connection->query("
        CREATE TABLE tipo_consignacion (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(255) NOT NULL,
            descripcion TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Tabla tipo_consignacion creada correctamente\n";
    
    // No insertar tipos de consignación predeterminados, se crearán automáticamente durante la sincronización
    echo "Tabla tipo_consignacion creada sin datos predeterminados\n";
    
    // Crear tabla de características
    $connection->query("
        CREATE TABLE caracteristicas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(255) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Tabla caracteristicas creada correctamente\n";
    
    // Crear tabla pivote caracteristica_inmueble (siguiendo convenciones de Laravel)
    $connection->query("
        CREATE TABLE caracteristica_inmueble (
            id INT AUTO_INCREMENT PRIMARY KEY,
            inmueble_id INT NOT NULL,
            caracteristica_id INT NOT NULL,
            valor VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (inmueble_id),
            INDEX (caracteristica_id),
            UNIQUE KEY caracteristica_inmueble_inmueble_id_caracteristica_id_unique (inmueble_id, caracteristica_id),
            FOREIGN KEY (inmueble_id) REFERENCES inmuebles(id) ON DELETE CASCADE,
            FOREIGN KEY (caracteristica_id) REFERENCES caracteristicas(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Tabla caracteristica_inmueble creada correctamente\n";
    
} catch (Exception $e) {
    echo "Error al crear tablas de catálogos: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nReinicio de la base de datos completado correctamente.\n";
echo "Puede ejecutar 'php sync.php' para iniciar una nueva sincronización.\n";
