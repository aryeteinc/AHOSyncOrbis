<?php
/**
 * Script para preparar SyncOrbisPhp para producción
 * 
 * Este script:
 * 1. Verifica la estructura de directorios
 * 2. Crea un archivo .htaccess para proteger el acceso web
 * 3. Establece los permisos correctos
 * 4. Verifica la configuración del archivo .env
 */

echo "=== Preparando SyncOrbisPhp para producción ===\n\n";

// Directorio base
$baseDir = __DIR__;
echo "Directorio base: $baseDir\n";

// 1. Verificar estructura de directorios
echo "\n[1/4] Verificando estructura de directorios...\n";
$requiredDirs = [
    'commands',
    'config',
    'logs',
    'public/images',
    'src'
];

foreach ($requiredDirs as $dir) {
    $fullPath = "$baseDir/$dir";
    if (!is_dir($fullPath)) {
        echo "Creando directorio: $dir\n";
        mkdir($fullPath, 0755, true);
    } else {
        echo "✓ Directorio $dir existe\n";
    }
}

// 2. Crear archivo .htaccess para proteger acceso web
echo "\n[2/4] Creando archivo .htaccess para proteger acceso web...\n";
$htaccessContent = "# Denegar acceso a todos los archivos
Order deny,allow
Deny from all

# Permitir acceso a archivos específicos si es necesario
# <Files \"archivo-especifico.php\">
#    Allow from all
# </Files>
";

file_put_contents("$baseDir/.htaccess", $htaccessContent);
echo "✓ Archivo .htaccess creado\n";

// 3. Establecer permisos correctos
echo "\n[3/4] Estableciendo permisos correctos...\n";

// Permisos para directorios
$dirs = [
    "$baseDir/public/images" => 0755,
    "$baseDir/logs" => 0755
];

foreach ($dirs as $dir => $perm) {
    if (is_dir($dir)) {
        chmod($dir, $perm);
        echo "✓ Permisos establecidos para $dir\n";
    } else {
        echo "⚠ No se pudo establecer permisos para $dir (no existe)\n";
    }
}

// Permisos para archivos de comandos
$commandFiles = glob("$baseDir/commands/*.php");
foreach ($commandFiles as $file) {
    chmod($file, 0755);
    echo "✓ Permisos de ejecución establecidos para " . basename($file) . "\n";
}

// 4. Verificar configuración .env
echo "\n[4/4] Verificando configuración .env...\n";
$envFile = "$baseDir/config/.env";
$envExampleFile = "$baseDir/config/.env.example";

if (!file_exists($envFile) && file_exists($envExampleFile)) {
    copy($envExampleFile, $envFile);
    echo "✓ Archivo .env creado a partir de .env.example\n";
    echo "⚠ IMPORTANTE: Edita el archivo config/.env con tus datos de producción\n";
} elseif (file_exists($envFile)) {
    echo "✓ Archivo .env existe\n";
    
    // Leer el archivo .env para verificar configuración
    $envContent = file_get_contents($envFile);
    
    // Verificar configuraciones importantes
    $checks = [
        'DB_HOST' => 'Verifica que DB_HOST esté configurado correctamente',
        'DB_DATABASE' => 'Configura el nombre de la base de datos',
        'DB_USERNAME' => 'Configura el usuario de la base de datos',
        'DB_PASSWORD' => 'Configura la contraseña de la base de datos',
        'IMAGES_STORAGE_MODE' => 'Asegúrate de que IMAGES_STORAGE_MODE esté configurado (recomendado: laravel)'
    ];
    
    foreach ($checks as $key => $message) {
        if (!preg_match("/$key\s*=\s*.+/", $envContent)) {
            echo "⚠ $message\n";
        }
    }
} else {
    echo "⚠ No se encontró archivo .env ni .env.example. Debes crear el archivo config/.env manualmente.\n";
}

echo "\n=== Preparación completada ===\n";
echo "Tu aplicación está lista para ser subida a un servidor de producción.\n";
echo "Consulta docs/configuracion_servidor.md para más detalles sobre cómo configurar en Namecheap.\n";
