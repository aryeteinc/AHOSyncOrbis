<?php
/**
 * Script para depurar la carga de variables de entorno
 */

echo "=== Depuración de variables de entorno ===\n\n";

// Verificar si el archivo .env existe
$envPath = __DIR__ . '/../config/.env';
echo "Verificando archivo .env en: $envPath\n";
if (file_exists($envPath)) {
    echo "✓ El archivo .env existe\n";
    echo "Permisos del archivo: " . substr(sprintf('%o', fileperms($envPath)), -4) . "\n";
    echo "Tamaño del archivo: " . filesize($envPath) . " bytes\n\n";
    
    // Intentar cargar manualmente el archivo .env
    echo "Contenido del archivo .env:\n";
    echo "----------------------------------------\n";
    $envContent = file_get_contents($envPath);
    echo $envContent . "\n";
    echo "----------------------------------------\n\n";
    
    // Cargar el EnvLoader
    require_once __DIR__ . '/../src/EnvLoader.php';
    echo "Cargando variables con EnvLoader...\n";
    EnvLoader::load($envPath);
    echo "Variables cargadas.\n\n";
} else {
    echo "✗ El archivo .env NO existe\n\n";
}

// Mostrar las variables de entorno relacionadas con la base de datos
echo "Variables de entorno de base de datos:\n";
echo "DB_HOST: " . (getenv('DB_HOST') ?: 'No definido') . "\n";
echo "DB_PORT: " . (getenv('DB_PORT') ?: 'No definido') . "\n";
echo "DB_DATABASE: " . (getenv('DB_DATABASE') ?: 'No definido') . "\n";
echo "DB_USERNAME: " . (getenv('DB_USERNAME') ?: 'No definido') . "\n";
echo "DB_PASSWORD: " . (getenv('DB_PASSWORD') ?: 'No definido') . "\n\n";

// Verificar si hay valores hardcodeados en Database.php
echo "Valores que se usarían en Database.php:\n";
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$database = getenv('DB_DATABASE') ?: 'OrbisAHOPHP';
$username = getenv('DB_USERNAME') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';

echo "Host efectivo: $host\n";
echo "Puerto efectivo: $port\n";
echo "Base de datos efectiva: $database\n";
echo "Usuario efectivo: $username\n";
echo "Contraseña efectiva: " . (empty($password) ? 'vacía' : '[configurada]') . "\n\n";

// Intentar conectar a la base de datos
echo "Intentando conexión a la base de datos...\n";
try {
    $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    ];
    
    $connection = new PDO($dsn, $username, $password, $options);
    echo "✓ Conexión exitosa a la base de datos\n";
    
    // Verificar la versión de MySQL
    $stmt = $connection->query('SELECT VERSION() as version');
    $row = $stmt->fetch();
    echo "Versión de MySQL: " . $row['version'] . "\n";
    
    // Verificar tablas existentes
    $stmt = $connection->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tablas existentes: " . (count($tables) > 0 ? implode(', ', $tables) : 'Ninguna') . "\n";
    
} catch (PDOException $e) {
    echo "✗ Error de conexión: " . $e->getMessage() . "\n";
}

echo "\n=== Fin de la depuración ===\n";
