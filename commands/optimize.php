<?php
/**
 * Script para optimizar el rendimiento del sistema SyncOrbisPhp
 * 
 * Este script aplica varias optimizaciones para mejorar la velocidad de sincronización:
 * - Optimiza la estructura de la base de datos
 * - Crea índices para mejorar las consultas
 * - Configura el caché para datos frecuentemente utilizados
 * - Ajusta la configuración de PHP para mejor rendimiento
 * 
 * Uso: php commands/optimize.php [--force]
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Optimizer.php';
require_once __DIR__ . '/../src/CacheManager.php';
require_once __DIR__ . '/../src/Logger.php';

// Verificar opciones
$options = getopt('', ['force']);
$force = isset($options['force']);

// Inicializar logger
$logger = new Logger('optimize');

// Mostrar encabezado
$logger->info('=== OPTIMIZADOR DE RENDIMIENTO SYNCORBISPHP ===');
$logger->info('Iniciando proceso de optimización...');

// Verificar directorios necesarios
$cacheDir = __DIR__ . '/../cache';
if (!is_dir($cacheDir)) {
    $logger->info("Creando directorio de caché: {$cacheDir}");
    mkdir($cacheDir, 0755, true);
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

// Inicializar optimizador
$optimizer = new Optimizer($connection, function($message) use ($logger) {
    $logger->info($message);
});

// Inicializar gestor de caché
$cacheManager = new CacheManager($cacheDir, true, 3600, function($message) use ($logger) {
    $logger->debug($message);
});

// Mostrar estadísticas iniciales
$logger->info('Obteniendo estadísticas iniciales...');

// Verificar tablas existentes
try {
    $tables = $db->fetchAll("SHOW TABLES");
    $tableCount = count($tables);
    $logger->info("Tablas encontradas: {$tableCount}");
    
    // Verificar tabla de inmuebles
    $inmueblesExists = false;
    foreach ($tables as $table) {
        $tableName = reset($table);
        if ($tableName === 'inmuebles') {
            $inmueblesExists = true;
            break;
        }
    }
    
    if (!$inmueblesExists) {
        $logger->warning('La tabla inmuebles no existe. Algunas optimizaciones no se aplicarán.');
    } else {
        // Contar registros en inmuebles
        $count = $db->fetchOne("SELECT COUNT(*) as total FROM inmuebles");
        $logger->info("Registros en tabla inmuebles: " . $count['total']);
    }
} catch (Exception $e) {
    $logger->error('Error al verificar tablas: ' . $e->getMessage());
}

// Optimizar configuración de PHP
$logger->info('Optimizando configuración de PHP...');
$phpSettings = $optimizer->optimizePhpSettings();
$logger->info('Configuración de PHP optimizada:');
$logger->info('- Memoria: ' . $phpSettings['original']['memory_limit'] . ' -> ' . $phpSettings['new']['memory_limit']);
$logger->info('- Tiempo de ejecución: ' . $phpSettings['original']['max_execution_time'] . 's -> ' . $phpSettings['new']['max_execution_time'] . 's');
$logger->info('- Timeout de socket: ' . $phpSettings['original']['default_socket_timeout'] . 's -> ' . $phpSettings['new']['default_socket_timeout'] . 's');

// Optimizar estructura de tablas
$logger->info('Optimizando estructura de tablas...');
$tableOptimized = $optimizer->optimizeTableStructure();
if ($tableOptimized) {
    $logger->info('Estructura de tablas optimizada correctamente');
} else {
    $logger->warning('No se pudo optimizar la estructura de tablas');
}

// Crear índices para mejorar el rendimiento
$logger->info('Creando índices para mejorar el rendimiento...');
$indexesCreated = $optimizer->createIndexes();
if ($indexesCreated) {
    $logger->info('Índices creados correctamente');
} else {
    $logger->warning('No se pudieron crear todos los índices');
}

// Optimizar la base de datos (ANALYZE y OPTIMIZE)
$logger->info('Optimizando tablas de la base de datos...');
$dbOptimized = $optimizer->optimizeDatabase();
if ($dbOptimized) {
    $logger->info('Base de datos optimizada correctamente');
} else {
    $logger->warning('No se pudo optimizar completamente la base de datos');
}

// Configurar caché
$logger->info('Configurando sistema de caché...');
$cacheManager->clear(); // Limpiar caché existente
$cacheStats = $cacheManager->getStats();
$logger->info('Sistema de caché configurado:');
$logger->info('- Directorio: ' . $cacheStats['directory']);
$logger->info('- TTL: ' . $cacheStats['ttl'] . ' segundos');
$logger->info('- Estado: ' . ($cacheStats['enabled'] ? 'Habilitado' : 'Deshabilitado'));

// Crear archivo de configuración de caché
$cacheConfig = [
    'enabled' => true,
    'ttl' => 3600,
    'directory' => $cacheDir
];

$cacheConfigFile = __DIR__ . '/../config/cache.php';
$cacheConfigContent = "<?php\n\n// Configuración de caché generada por el optimizador\nreturn " . var_export($cacheConfig, true) . ";\n";
file_put_contents($cacheConfigFile, $cacheConfigContent);
$logger->info('Archivo de configuración de caché creado: ' . $cacheConfigFile);

// Crear directorio para almacenar imágenes en caché
$imageCacheDir = __DIR__ . '/../public/cache/images';
if (!is_dir($imageCacheDir)) {
    $logger->info("Creando directorio de caché de imágenes: {$imageCacheDir}");
    mkdir($imageCacheDir, 0755, true);
}

// Verificar y crear archivo .htaccess para el caché
$htaccessFile = $cacheDir . '/.htaccess';
if (!file_exists($htaccessFile)) {
    $htaccessContent = "# Denegar acceso a archivos de caché\nDeny from all\n";
    file_put_contents($htaccessFile, $htaccessContent);
    $logger->info('Archivo .htaccess creado para proteger el caché');
}

// Crear archivo de recomendaciones
$recommendationsFile = __DIR__ . '/../docs/recomendaciones_rendimiento.md';
$recommendationsContent = "# Recomendaciones para Mejorar el Rendimiento

Este documento contiene recomendaciones para mejorar el rendimiento de SyncOrbisPhp.

## Configuración de PHP

Para obtener el mejor rendimiento, configure los siguientes valores en su archivo `php.ini`:

```ini
; Aumentar el límite de memoria
memory_limit = 256M

; Aumentar el tiempo máximo de ejecución
max_execution_time = 300

; Aumentar el tiempo de espera para conexiones externas
default_socket_timeout = 60

; Habilitar el caché de opcode
opcache.enable = 1
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 8
opcache.max_accelerated_files = 4000
```

## Configuración de MySQL

Para mejorar el rendimiento de MySQL, considere los siguientes ajustes:

```sql
-- Aumentar el tamaño del buffer de consultas
SET GLOBAL query_cache_size = 32M;

-- Aumentar el tamaño del buffer de ordenación
SET GLOBAL sort_buffer_size = 4M;

-- Aumentar el tamaño del buffer de claves
SET GLOBAL key_buffer_size = 64M;

-- Aumentar el tamaño del buffer de lectura
SET GLOBAL read_buffer_size = 2M;
```

## Estrategias de Sincronización

1. **Sincronización Incremental**: Use la opción `--limit` para sincronizar en lotes pequeños.

2. **Sincronización Nocturna**: Configure un trabajo cron para ejecutar la sincronización durante la noche.

3. **Sincronización Selectiva**: Use la opción `--no-images` si solo necesita actualizar los datos y no las imágenes.

4. **Procesamiento por Lotes**: El sistema ahora utiliza procesamiento por lotes para mejorar el rendimiento.

## Monitoreo de Rendimiento

Para monitorear el rendimiento, revise los archivos de registro en la carpeta `logs/`. Estos archivos contienen información detallada sobre el tiempo de ejecución y el uso de recursos.

## Optimización Periódica

Ejecute el script de optimización periódicamente para mantener el rendimiento óptimo:

```bash
php commands/optimize.php
```

Este script optimiza la estructura de la base de datos, crea índices y configura el caché para mejorar el rendimiento.
";

file_put_contents($recommendationsFile, $recommendationsContent);
$logger->info('Archivo de recomendaciones de rendimiento creado: ' . $recommendationsFile);

// Actualizar README.md para incluir información sobre optimización
try {
    $readmePath = __DIR__ . '/../README.md';
    if (file_exists($readmePath)) {
        $readme = file_get_contents($readmePath);
        
        // Verificar si ya existe la sección de optimización
        if (strpos($readme, '## Optimización de Rendimiento') === false) {
            // Añadir sección de optimización
            $optimizationSection = "\n\n## Optimización de Rendimiento\n\n";
            $optimizationSection .= "Para optimizar el rendimiento del sistema, ejecute el siguiente comando:\n\n";
            $optimizationSection .= "```bash\nphp commands/optimize.php\n```\n\n";
            $optimizationSection .= "Este comando aplicará varias optimizaciones para mejorar la velocidad de sincronización:\n\n";
            $optimizationSection .= "- Optimizará la estructura de la base de datos\n";
            $optimizationSection .= "- Creará índices para mejorar las consultas\n";
            $optimizationSection .= "- Configurará el caché para datos frecuentemente utilizados\n";
            $optimizationSection .= "- Ajustará la configuración de PHP para mejor rendimiento\n\n";
            $optimizationSection .= "Para más información, consulte [Recomendaciones de Rendimiento](docs/recomendaciones_rendimiento.md).\n";
            
            // Añadir al final del archivo
            $readme .= $optimizationSection;
            file_put_contents($readmePath, $readme);
            $logger->info('README.md actualizado con información de optimización');
        } else {
            $logger->info('La sección de optimización ya existe en README.md');
        }
    }
} catch (Exception $e) {
    $logger->warning('No se pudo actualizar README.md: ' . $e->getMessage());
}

// Finalizar
$logger->info('=== PROCESO DE OPTIMIZACIÓN COMPLETADO ===');
$logger->info('El sistema ha sido optimizado para un mejor rendimiento.');
$logger->info('Para más información, consulte: docs/recomendaciones_rendimiento.md');

// Mostrar recomendaciones finales
echo "\n";
echo "=================================================\n";
echo "  OPTIMIZACIÓN COMPLETADA EXITOSAMENTE\n";
echo "=================================================\n";
echo "\n";
echo "El sistema ha sido optimizado para un mejor rendimiento.\n";
echo "Se han aplicado las siguientes mejoras:\n";
echo "- Optimización de la estructura de la base de datos\n";
echo "- Creación de índices para mejorar las consultas\n";
echo "- Configuración del sistema de caché\n";
echo "- Ajuste de la configuración de PHP\n";
echo "\n";
echo "Para más información, consulte:\n";
echo "docs/recomendaciones_rendimiento.md\n";
echo "\n";
echo "Para ejecutar una sincronización optimizada, use:\n";
echo "php sync.php --batch-size=50 --use-cache\n";
echo "\n";
