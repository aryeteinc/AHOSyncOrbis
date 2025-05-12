<?php
/**
 * Script para probar la conexión a la base de datos
 * 
 * Este script intenta conectarse a la base de datos y muestra información
 * sobre la conexión y las tablas existentes.
 * 
 * Uso: php commands/test-connection.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Logger.php';

// Inicializar logger
$logger = new Logger('test-connection', true);
$logger->info('=== PRUEBA DE CONEXIÓN A LA BASE DE DATOS ===');

// Mostrar información de configuración
$logger->info('Configuración de conexión:');
$logger->info('- Host: ' . DB_HOST);
$logger->info('- Puerto: ' . DB_PORT);
$logger->info('- Base de datos: ' . DB_NAME);
$logger->info('- Usuario: ' . DB_USER);

// Intentar conectar a la base de datos
try {
    $logger->info('Intentando conectar a la base de datos...');
    
    // Obtener instancia de la base de datos usando el patrón Singleton
    $db = Database::getInstance();
    $connection = $db->getConnection();
    
    $logger->info('¡Conexión establecida correctamente!');
    
    // Obtener información del servidor
    $serverInfo = $connection->getAttribute(PDO::ATTR_SERVER_VERSION);
    $logger->info('Versión del servidor: ' . $serverInfo);
    
    // Verificar tablas existentes
    $logger->info('Verificando tablas existentes...');
    
    $stmt = $connection->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($tables) > 0) {
        $logger->info('Tablas encontradas: ' . count($tables));
        foreach ($tables as $table) {
            $countStmt = $connection->query("SELECT COUNT(*) FROM `{$table}`");
            $count = $countStmt->fetchColumn();
            $logger->info("- {$table}: {$count} registros");
        }
    } else {
        $logger->warning('No se encontraron tablas en la base de datos.');
        $logger->info('Ejecute el script de reset para crear las tablas:');
        $logger->info('php commands/reset.php --confirm');
    }
    
    // Verificar si las tablas principales existen
    $requiredTables = ['inmuebles', 'imagenes', 'cambios_inmuebles', 'inmuebles_estado'];
    $missingTables = [];
    
    foreach ($requiredTables as $table) {
        if (!in_array($table, $tables)) {
            $missingTables[] = $table;
        }
    }
    
    if (count($missingTables) > 0) {
        $logger->warning('Faltan las siguientes tablas requeridas:');
        foreach ($missingTables as $table) {
            $logger->warning("- {$table}");
        }
        $logger->info('Ejecute el script de reset para crear las tablas:');
        $logger->info('php commands/reset.php --confirm');
    } else if (count($tables) >= count($requiredTables)) {
        $logger->info('Todas las tablas requeridas están presentes.');
    }
    
    // Verificar columnas en la tabla inmuebles
    if (in_array('inmuebles', $tables)) {
        $logger->info('Verificando columnas de la tabla inmuebles...');
        
        $columnsStmt = $connection->query("SHOW COLUMNS FROM inmuebles");
        $columns = $columnsStmt->fetchAll(PDO::FETCH_COLUMN);
        
        $requiredColumns = ['id', 'ref', 'titulo', 'activo', 'asesor_id', 'destacado', 'hash_datos'];
        $missingColumns = [];
        
        foreach ($requiredColumns as $column) {
            if (!in_array($column, $columns)) {
                $missingColumns[] = $column;
            }
        }
        
        if (count($missingColumns) > 0) {
            $logger->warning('Faltan las siguientes columnas en la tabla inmuebles:');
            foreach ($missingColumns as $column) {
                $logger->warning("- {$column}");
            }
        } else {
            $logger->info('Todas las columnas requeridas están presentes en la tabla inmuebles.');
        }
    }
    
    $logger->info('=== PRUEBA DE CONEXIÓN COMPLETADA ===');
    
} catch (Exception $e) {
    $logger->error('Error al conectar a la base de datos: ' . $e->getMessage());
    
    // Mostrar sugerencias para solucionar el problema
    $logger->info('Sugerencias para solucionar el problema:');
    $logger->info('1. Verifique que las credenciales en config/.env sean correctas');
    $logger->info('2. Asegúrese de que la base de datos exista y esté accesible');
    $logger->info('3. Si está usando Docker, verifique que los contenedores estén en ejecución:');
    $logger->info('   docker-compose ps');
    $logger->info('4. Si los contenedores no están en ejecución, inícielos:');
    $logger->info('   docker-compose up -d');
    
    exit(1);
}
