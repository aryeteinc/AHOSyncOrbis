<?php
/**
 * Script para eliminar todas las tablas de la base de datos
 * Uso: php commands/delete-db.php [--confirm]
 */

// Cargar clases necesarias
require_once __DIR__ . '/../src/Database.php';

// Verificar confirmación
$confirm = false;
foreach ($argv as $arg) {
    if ($arg === '--confirm') {
        $confirm = true;
        break;
    }
}

if (!$confirm) {
    echo "ADVERTENCIA: Este script eliminará TODAS las tablas de la base de datos.\n";
    echo "Para confirmar, ejecute el script con el parámetro --confirm\n";
    echo "Ejemplo: php commands/delete-db.php --confirm\n";
    exit(1);
}

try {
    // Conectar a la base de datos
    $db = Database::getInstance();
    $connection = $db->getConnection();
    
    echo "Conexión a la base de datos establecida\n";
    
    // Desactivar restricciones de clave foránea temporalmente
    $connection->exec('SET FOREIGN_KEY_CHECKS = 0');
    
    // Obtener todas las tablas
    $stmt = $connection->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tables)) {
        echo "No hay tablas para eliminar.\n";
    } else {
        echo "Se eliminarán las siguientes tablas:\n";
        foreach ($tables as $table) {
            echo "- $table\n";
        }
        
        // Eliminar cada tabla
        foreach ($tables as $table) {
            $connection->exec("DROP TABLE IF EXISTS `$table`");
            echo "Tabla `$table` eliminada.\n";
        }
        
        echo "\nTodas las tablas han sido eliminadas correctamente.\n";
    }
    
    // Reactivar restricciones de clave foránea
    $connection->exec('SET FOREIGN_KEY_CHECKS = 1');
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
