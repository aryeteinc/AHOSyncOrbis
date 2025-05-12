<?php
/**
 * Script para borrar todas las imágenes físicas
 * 
 * Este script borra todas las imágenes físicas del directorio configurado.
 * 
 * Uso: php commands/clear-images.php [--confirm]
 */

require_once __DIR__ . '/../config/config.php';

// Verificar confirmación
$options = getopt('', ['confirm']);
$confirmed = isset($options['confirm']);

if (!$confirmed) {
    echo "ADVERTENCIA: Este script eliminará TODAS las imágenes físicas.\n";
    echo "Para confirmar, ejecute el script con la opción --confirm\n";
    echo "Ejemplo: php commands/clear-images.php --confirm\n";
    exit(1);
}

echo "Borrando imágenes físicas...\n";

// Usar la ruta absoluta correcta en lugar de la constante
$imagesFolder = '/Users/joseflorez/SyncOrbisPhp/public/images/inmuebles';

echo "Directorio de imágenes configurado: {$imagesFolder}\n";

// Verificar si el directorio existe
if (!is_dir($imagesFolder)) {
    echo "El directorio de imágenes no existe: {$imagesFolder}\n";
    exit(1);
}

// Verificar permisos
if (!is_readable($imagesFolder) || !is_writable($imagesFolder)) {
    echo "Error: El directorio de imágenes no tiene permisos de lectura/escritura\n";
    echo "Permisos actuales: " . substr(sprintf('%o', fileperms($imagesFolder)), -4) . "\n";
    exit(1);
}

// Función para obtener todos los archivos recursivamente
function getAllFiles($dir) {
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $files[] = $file->getPathname();
        }
    }
    
    return $files;
}

// Obtener todos los archivos recursivamente
$files = getAllFiles($imagesFolder);
echo "Archivos encontrados: " . count($files) . "\n";

// Mostrar información detallada de los archivos
echo "\nInformación detallada de los archivos:\n";
foreach ($files as $index => $file) {
    if (is_file($file)) {
        $perms = substr(sprintf('%o', fileperms($file)), -4);
        $owner = posix_getpwuid(fileowner($file));
        $group = posix_getgrgid(filegroup($file));
        $size = filesize($file);
        
        echo "[$index] {$file}\n";
        echo "  - Permisos: {$perms}\n";
        echo "  - Propietario: {$owner['name']}\n";
        echo "  - Grupo: {$group['name']}\n";
        echo "  - Tamaño: {$size} bytes\n";
    }
}

// Intentar borrar usando unlink de PHP
echo "\nIntentando borrar con unlink()...\n";
$count = 0;
foreach ($files as $file) {
    if (is_file($file)) {
        echo "Borrando: {$file}\n";
        if (unlink($file)) {
            $count++;
        } else {
            echo "Error al borrar el archivo: {$file}\n";
        }
    }
}

echo "Se han borrado {$count} imágenes con unlink()\n";

// Si no se borraron todos los archivos, intentar con el comando rm
if ($count < count($files)) {
    echo "\nIntentando borrar con el comando rm...\n";
    $rmCount = 0;
    
    foreach ($files as $file) {
        if (is_file($file)) {
            $command = "rm -f " . escapeshellarg($file);
            echo "Ejecutando: {$command}\n";
            system($command, $returnCode);
            
            if ($returnCode === 0) {
                $rmCount++;
            } else {
                echo "Error al ejecutar rm: código {$returnCode}\n";
            }
        }
    }
    
    echo "Se han borrado {$rmCount} imágenes adicionales con rm\n";
    $count += $rmCount;
}

echo "\nTotal de imágenes borradas: {$count}\n";
