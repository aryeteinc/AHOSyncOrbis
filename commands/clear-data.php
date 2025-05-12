<?php
/**
 * Comando para borrar todos los datos de las tablas pero manteniendo la estructura
 * 
 * Este comando elimina todos los registros de todas las tablas pero mantiene
 * la estructura de la base de datos intacta.
 * 
 * Uso:
 * php commands/clear-data.php [--with-images]
 * 
 * Opciones:
 * --with-images    Eliminar también las imágenes físicas
 */

// Cargar configuración y variables de entorno
require_once __DIR__ . '/../src/EnvLoader.php';
EnvLoader::load(__DIR__ . '/../config/.env');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Database.php';

echo "======================================================================\n";
echo "ELIMINACIÓN DE DATOS DE LA BASE DE DATOS\n";
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

// Verificar si se debe eliminar las imágenes
$deleteImages = in_array('--with-images', $argv);

// Conectar a la base de datos
try {
    $db = Database::getInstance();
    $connection = $db->getConnection();
    echo "Conexión a la base de datos establecida\n";
    
    // Desactivar restricciones de clave foránea temporalmente
    $connection->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Obtener todas las tablas
    $stmt = $connection->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Eliminando datos de las tablas...\n";
    
    // Truncar todas las tablas
    foreach ($tables as $table) {
        $connection->exec("TRUNCATE TABLE `{$table}`");
        echo "Datos de la tabla `{$table}` eliminados.\n";
    }
    
    // Reactivar restricciones de clave foránea
    $connection->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "\nTodos los datos han sido eliminados correctamente.\n\n";
    
    // Eliminar imágenes si se especificó la opción
    if ($deleteImages) {
        echo "Eliminando imágenes físicas...\n";
        if (is_dir($imagesFolder)) {
            echo "Buscando imágenes en: $imagesFolder\n";
            $deleted = deleteAllImages($imagesFolder);
            echo "Se eliminaron {$deleted['files']} imágenes y {$deleted['directories']} carpetas vacías de la carpeta $imagesFolder\n\n";
        } else {
            echo "La carpeta de imágenes no existe: $imagesFolder\n\n";
        }
    }
    
    echo "Operación completada con éxito.\n";
    
} catch (PDOException $e) {
    echo "Error de conexión a la base de datos: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Elimina todas las imágenes de un directorio y sus subdirectorios, y elimina las carpetas vacías
 * 
 * @param string $dir Directorio a procesar
 * @return array Array con el número de archivos y carpetas eliminados
 */
function deleteAllImages($dir) {
    $countFiles = 0;
    $countDirs = 0;
    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
    
    if (!is_dir($dir)) {
        return ['files' => 0, 'directories' => 0];
    }
    
    // Primero recolectamos todas las carpetas en un array para procesarlas después
    $directories = [];
    
    $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
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
    foreach ($directories as $dirPath) {
        // Verificar que no sea la carpeta principal
        if ($dirPath !== $dir) {
            // Verificar si la carpeta está vacía
            if (is_dir($dirPath) && count(scandir($dirPath)) <= 2) { // . y .. siempre están presentes
                if (rmdir($dirPath)) {
                    $countDirs++;
                }
            }
        }
    }
    
    return ['files' => $countFiles, 'directories' => $countDirs];
}
