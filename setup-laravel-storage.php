<?php
/**
 * Script para configurar el almacenamiento de Laravel para SyncOrbisPhp
 * Este script verifica y crea la estructura de directorios necesaria en Laravel
 */

// Cargar configuración
require_once __DIR__ . '/config/config.php';

echo "Configurando almacenamiento para integración con Laravel...\n";

// Verificar modo de almacenamiento
if (IMAGES_STORAGE_MODE !== 'laravel') {
    echo "Error: El modo de almacenamiento no está configurado como 'laravel' en el archivo .env\n";
    echo "Por favor, configure IMAGES_STORAGE_MODE=laravel en el archivo .env\n";
    exit(1);
}

// Verificar ruta de storage de Laravel
if (empty(LARAVEL_STORAGE_PATH)) {
    echo "Error: La ruta de storage de Laravel no está configurada en el archivo .env\n";
    echo "Por favor, configure LARAVEL_STORAGE_PATH en el archivo .env\n";
    exit(1);
}

// Verificar que el directorio de Laravel exista
$laravelBasePath = preg_replace('/\/storage\/app\/public$/', '', LARAVEL_STORAGE_PATH);
if (!is_dir($laravelBasePath)) {
    echo "Error: La ruta base de Laravel no existe: {$laravelBasePath}\n";
    echo "Por favor, verifique la ruta configurada en LARAVEL_STORAGE_PATH\n";
    exit(1);
}

// Verificar que el directorio storage/app exista
$storageAppPath = $laravelBasePath . '/storage/app';
if (!is_dir($storageAppPath)) {
    echo "Error: El directorio storage/app de Laravel no existe: {$storageAppPath}\n";
    echo "Por favor, verifique que la instalación de Laravel sea correcta\n";
    exit(1);
}

// Verificar que el directorio storage/app/public exista
$storagePublicPath = $storageAppPath . '/public';
if (!is_dir($storagePublicPath)) {
    echo "Creando directorio storage/app/public: {$storagePublicPath}\n";
    if (!mkdir($storagePublicPath, 0755, true)) {
        echo "Error: No se pudo crear el directorio storage/app/public\n";
        exit(1);
    }
}

// Construir la ruta completa al directorio de imágenes en Laravel
$imagesPath = rtrim(LARAVEL_STORAGE_PATH, '/') . '/' . LARAVEL_IMAGES_PATH;

echo "Verificando directorio de imágenes en Laravel: {$imagesPath}\n";

// Crear el directorio si no existe
if (!is_dir($imagesPath)) {
    echo "Creando directorio de imágenes en Laravel: {$imagesPath}\n";
    
    if (!mkdir($imagesPath, 0755, true)) {
        echo "Error: No se pudo crear el directorio de imágenes en Laravel\n";
        echo "Intentando con el comando mkdir...\n";
        
        $command = "mkdir -p " . escapeshellarg($imagesPath);
        system($command, $returnCode);
        
        if ($returnCode !== 0) {
            echo "Error: No se pudo crear el directorio con el comando mkdir\n";
            exit(1);
        }
    }
    
    echo "Directorio de imágenes creado correctamente\n";
} else {
    echo "El directorio de imágenes ya existe\n";
}

// Verificar permisos
if (!is_writable($imagesPath)) {
    echo "Advertencia: El directorio de imágenes no tiene permisos de escritura\n";
    echo "Ajustando permisos...\n";
    
    chmod($imagesPath, 0755);
    
    if (!is_writable($imagesPath)) {
        echo "Error: No se pudieron ajustar los permisos del directorio\n";
        echo "Por favor, ajuste los permisos manualmente: chmod -R 755 {$imagesPath}\n";
        exit(1);
    }
    
    echo "Permisos ajustados correctamente\n";
} else {
    echo "El directorio de imágenes tiene los permisos correctos\n";
}

// Verificar si el enlace simbólico existe
$publicLinkPath = $laravelBasePath . '/public/storage';
if (!file_exists($publicLinkPath)) {
    echo "\nAdvertencia: El enlace simbólico de storage no existe en Laravel\n";
    echo "Debe ejecutar el siguiente comando en su proyecto Laravel:\n";
    echo "  php artisan storage:link\n";
    echo "Esto creará un enlace simbólico de 'public/storage' a 'storage/app/public'\n";
}

echo "\nConfiguración completada correctamente!\n";
echo "Las imágenes se guardarán en: {$imagesPath}\n";
echo "En Laravel, podrás acceder a las imágenes usando: Storage::disk('" . LARAVEL_DISK . "')->url('" . LARAVEL_IMAGES_PATH . "/inmueble_XXX/imagen.jpg')\n";
echo "Donde XXX es la referencia del inmueble\n";
echo "\nRecuerda ejecutar 'php artisan storage:link' en tu proyecto Laravel si no lo has hecho aún\n";
