<?php
/**
 * Clase para gestionar la conexión a la base de datos MySQL
 */
class Database {
    private static $instance = null;
    private $connection;
    
    /**
     * Constructor privado para implementar el patrón Singleton
     */
    private function __construct() {
        try {
            // Cargar variables de entorno desde .env si existe
            $envPath = __DIR__ . '/../config/.env';
            if (file_exists($envPath)) {
                require_once __DIR__ . '/EnvLoader.php';
                EnvLoader::load($envPath);
            }
            
            // Configuración directa de la base de datos para Namecheap
            // Modifica estos valores con tus credenciales reales de Namecheap
            $host = 'localhost'; // Normalmente es 'localhost' en Namecheap
            $port = '3306';
            $database = 'tu_usuario_nombrebd'; // Reemplaza con tu nombre de base de datos real
            $username = 'tu_usuario_nombreusuario'; // Reemplaza con tu nombre de usuario real
            $password = 'tu_contraseña'; // Reemplaza con tu contraseña real
            
            // Intenta obtener valores desde .env solo si existen
            if (getenv('DB_HOST')) $host = getenv('DB_HOST');
            if (getenv('DB_PORT')) $port = getenv('DB_PORT');
            if (getenv('DB_DATABASE')) $database = getenv('DB_DATABASE');
            if (getenv('DB_USERNAME')) $username = getenv('DB_USERNAME');
            if (getenv('DB_PASSWORD')) $password = getenv('DB_PASSWORD');
            
            // Usar formato adecuado para conexión TCP
            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                // Añadir comando de inicialización para asegurar conexión TCP
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
            ];
            
            $this->connection = new PDO($dsn, $username, $password, $options);
            
            if (getenv('DEBUG_MODE') === 'true') {
                echo "Conexión a la base de datos establecida correctamente.\n";
            }
        } catch (PDOException $e) {
            throw new Exception("Error de conexión a la base de datos: " . $e->getMessage());
        }
    }
    
    /**
     * Obtener la instancia de la base de datos (Singleton)
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Obtener la conexión PDO
     */
    public function getConnection() {
        return $this->connection;
    }
    
    /**
     * Ejecutar una consulta SQL
     * 
     * @param string $sql Consulta SQL
     * @param array $params Parámetros para la consulta
     * @return PDOStatement Resultado de la consulta
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                echo "Error en consulta SQL: " . $e->getMessage() . "\n";
                echo "SQL: " . $sql . "\n";
                echo "Parámetros: " . print_r($params, true) . "\n";
            }
            throw $e;
        }
    }
    
    /**
     * Obtener un solo registro
     * 
     * @param string $sql Consulta SQL
     * @param array $params Parámetros para la consulta
     * @return array|null Registro encontrado o null si no existe
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * Obtener múltiples registros
     * 
     * @param string $sql Consulta SQL
     * @param array $params Parámetros para la consulta
     * @return array Registros encontrados
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Insertar un registro y devolver el ID insertado
     * 
     * @param string $table Nombre de la tabla
     * @param array $data Datos a insertar (columna => valor)
     * @return int ID del registro insertado
     */
    public function insert($table, $data) {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        
        $this->query($sql, array_values($data));
        return $this->connection->lastInsertId();
    }
    
    /**
     * Actualizar un registro
     * 
     * @param string $table Nombre de la tabla
     * @param array $data Datos a actualizar (columna => valor)
     * @param string $where Condición WHERE
     * @param array $whereParams Parámetros para la condición WHERE
     * @return int Número de filas afectadas
     */
    public function update($table, $data, $where, $whereParams = []) {
        $setClauses = [];
        foreach (array_keys($data) as $column) {
            $setClauses[] = "{$column} = ?";
        }
        
        $sql = "UPDATE {$table} SET " . implode(', ', $setClauses) . " WHERE {$where}";
        
        $params = array_merge(array_values($data), $whereParams);
        $stmt = $this->query($sql, $params);
        
        return $stmt->rowCount();
    }
    
    /**
     * Eliminar registros
     * 
     * @param string $table Nombre de la tabla
     * @param string $where Condición WHERE
     * @param array $params Parámetros para la condición WHERE
     * @return int Número de filas afectadas
     */
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Verificar si una tabla existe
     * 
     * @param string $table Nombre de la tabla
     * @return bool True si la tabla existe, false en caso contrario
     */
    public function tableExists($table) {
        try {
            $result = $this->query("SHOW TABLES LIKE ?", [$table]);
            return $result->rowCount() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Verificar si una columna existe en una tabla
     * 
     * @param string $table Nombre de la tabla
     * @param string $column Nombre de la columna
     * @return bool True si la columna existe, false en caso contrario
     */
    public function columnExists($table, $column) {
        try {
            $result = $this->query("SHOW COLUMNS FROM {$table} LIKE ?", [$column]);
            return $result->rowCount() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Iniciar una transacción
     */
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    /**
     * Confirmar una transacción
     */
    public function commit() {
        return $this->connection->commit();
    }
    
    /**
     * Revertir una transacción
     */
    public function rollback() {
        return $this->connection->rollBack();
    }
    
    /**
     * Obtener la fecha y hora actual del servidor MySQL
     * 
     * @return string Fecha y hora actual en formato MySQL
     */
    public function now() {
        $result = $this->fetchOne("SELECT NOW() as now");
        return $result['now'];
    }
}
