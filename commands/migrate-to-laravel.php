<?php
/**
 * Script para migrar la base de datos a convenciones de Laravel
 * Este script ejecuta el archivo SQL de migración para renombrar tablas y columnas
 */

// Cargar configuración
require_once dirname(__DIR__) . '/config.php';

// Función para mostrar ayuda
function showHelp() {
    echo "Uso: php migrate-to-laravel.php [opciones]\n";
    echo "Opciones:\n";
    echo "  --help      Muestra esta ayuda\n";
    echo "  --confirm   Confirma la ejecución de la migración\n";
    echo "  --dry-run   Muestra las consultas que se ejecutarían sin aplicarlas\n";
    exit;
}

// Procesar argumentos
$confirm = false;
$dryRun = false;

foreach ($argv as $arg) {
    if ($arg === '--help') {
        showHelp();
    } elseif ($arg === '--confirm') {
        $confirm = true;
    } elseif ($arg === '--dry-run') {
        $dryRun = true;
    }
}

// Verificar confirmación
if (!$confirm && !$dryRun) {
    echo "ADVERTENCIA: Esta operación modificará la estructura de la base de datos.\n";
    echo "Se renombrarán tablas y columnas según las convenciones de Laravel.\n";
    echo "Ejecute con --confirm para proceder o --dry-run para ver las consultas sin ejecutarlas.\n";
    exit;
}

// Conectar a la base de datos
try {
    // Mostrar información de conexión para depuración
    echo "Intentando conectar a la base de datos con los siguientes parámetros:\n";
    echo "Host: " . DB_HOST . "\n";
    echo "Puerto: " . DB_PORT . "\n";
    echo "Base de datos: " . DB_DATABASE . "\n";
    echo "Usuario: " . DB_USERNAME . "\n";
    
    // Intentar conexión con socket TCP explícito
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_DATABASE . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        // Añadir comando de inicialización para asegurar conexión TCP
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    ];
    
    echo "Intentando conexión...\n";
    $db = new PDO($dsn, DB_USERNAME, DB_PASSWORD, $options);
    echo "Conexión a la base de datos establecida correctamente.\n";
} catch (PDOException $e) {
    echo "Error de conexión a la base de datos: " . $e->getMessage() . "\n";
    echo "\nIntentando conexión alternativa con localhost...\n";
    
    try {
        // Intentar con 'localhost' en lugar de IP
        $dsn = "mysql:host=localhost;port=" . DB_PORT . ";dbname=" . DB_DATABASE . ";charset=utf8mb4";
        $db = new PDO($dsn, DB_USERNAME, DB_PASSWORD, $options);
        echo "Conexión alternativa establecida correctamente.\n";
    } catch (PDOException $e2) {
        echo "Error en conexión alternativa: " . $e2->getMessage() . "\n";
        
        try {
            // Intentar con socket Unix (para macOS/Linux)
            $dsn = "mysql:unix_socket=/tmp/mysql.sock;dbname=" . DB_DATABASE . ";charset=utf8mb4";
            $db = new PDO($dsn, DB_USERNAME, DB_PASSWORD, $options);
            echo "Conexión mediante socket Unix establecida correctamente.\n";
        } catch (PDOException $e3) {
            echo "Error en conexión mediante socket: " . $e3->getMessage() . "\n";
            die("No se pudo establecer conexión con la base de datos. Verifique que MySQL esté en ejecución y que los parámetros de conexión sean correctos.\n");
        }
    }
}

// Ruta al archivo SQL de migración
$sqlFile = dirname(__DIR__) . '/docker/mysql/init/02-migrate-to-laravel.sql';

if (!file_exists($sqlFile)) {
    die("Error: No se encontró el archivo de migración: $sqlFile\n");
}

// Leer el archivo SQL
$sql = file_get_contents($sqlFile);
$queries = explode(';', $sql);

echo "\n" . str_repeat('=', 70) . "\n";
echo "MIGRACIÓN A CONVENCIONES DE LARAVEL\n";
echo str_repeat('=', 70) . "\n\n";

// Ejecutar cada consulta
$totalQueries = 0;
$successQueries = 0;
$errorQueries = 0;

// Iniciar transacción si no es dry run
if (!$dryRun) {
    $db->beginTransaction();
    echo "Iniciando transacción...\n";
}

foreach ($queries as $query) {
    $query = trim($query);
    
    if (empty($query)) {
        continue;
    }
    
    $totalQueries++;
    
    try {
        if ($dryRun) {
            echo "Consulta #$totalQueries:\n$query\n\n";
        } else {
            echo "Ejecutando consulta #$totalQueries...\n";
            $db->exec($query);
            echo "Consulta ejecutada correctamente.\n";
            $successQueries++;
        }
    } catch (PDOException $e) {
        echo "Error en consulta #$totalQueries: " . $e->getMessage() . "\n";
        echo "Consulta: $query\n\n";
        $errorQueries++;
        
        // Si no es dry run, hacer rollback en caso de error
        if (!$dryRun) {
            $db->rollBack();
            echo "Transacción revertida debido a errores.\n";
            die("La migración ha fallado.\n");
        }
    }
}

// Confirmar transacción si no es dry run y no hubo errores
if (!$dryRun) {
    if ($errorQueries === 0) {
        $db->commit();
        echo "Transacción confirmada correctamente.\n";
    } else {
        $db->rollBack();
        echo "Transacción revertida debido a errores.\n";
    }
}

// Mostrar resumen
echo "\n" . str_repeat('=', 70) . "\n";
echo "RESUMEN DE MIGRACIÓN\n";
echo str_repeat('=', 70) . "\n";
echo "Total de consultas: $totalQueries\n";

if ($dryRun) {
    echo "Modo: Simulación (no se aplicaron cambios)\n";
} else {
    echo "Consultas exitosas: $successQueries\n";
    echo "Consultas con error: $errorQueries\n";
    
    if ($errorQueries === 0) {
        echo "Estado: Migración completada correctamente\n";
    } else {
        echo "Estado: Migración fallida\n";
    }
}

echo str_repeat('=', 70) . "\n";
