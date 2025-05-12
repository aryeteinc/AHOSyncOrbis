<?php
/**
 * Clase para gestionar esquemas de base de datos
 * Permite crear tablas automáticamente y cambiar entre diferentes convenciones de nombres
 */
class SchemaManager {
    private $db;
    private $schema;
    private $schemaName;
    
    /**
     * Constructor
     * 
     * @param PDO $db Conexión a la base de datos
     * @param string $schemaName Nombre del esquema a utilizar (laravel, spanish, custom)
     */
    public function __construct($db, $schemaName = null) {
        $this->db = $db;
        
        // Cargar configuración de esquemas
        $schemaConfig = include __DIR__ . '/../config/schema_mappings.php';
        
        // Si no se especifica un esquema, usar el actual de la configuración
        if ($schemaName === null) {
            $schemaName = $schemaConfig['current_schema'];
        }
        
        $this->schemaName = $schemaName;
        
        // Verificar que el esquema exista
        if (!isset($schemaConfig['schemas'][$schemaName])) {
            throw new Exception("El esquema '$schemaName' no está definido en la configuración");
        }
        
        $this->schema = $schemaConfig['schemas'][$schemaName];
    }
    
    /**
     * Obtener el nombre del esquema actual
     * 
     * @return string Nombre del esquema actual
     */
    public function getSchemaName() {
        return $this->schemaName;
    }
    
    /**
     * Obtener el nombre de la tabla según el esquema actual
     * 
     * @param string $tableName Nombre de la tabla en inglés (convención Laravel)
     * @return string Nombre de la tabla según el esquema actual
     */
    public function getTableName($tableName) {
        // Si estamos en el esquema de Laravel, devolver el nombre tal cual
        if ($this->schemaName === 'laravel') {
            return $tableName;
        }
        
        // Si estamos en otro esquema, buscar el nombre correspondiente
        foreach ($this->schema['tables'] as $schemaTableName => $tableInfo) {
            if (isset($tableInfo['english_name']) && $tableInfo['english_name'] === $tableName) {
                return $schemaTableName;
            }
        }
        
        // Si no se encuentra, devolver el nombre original
        return $tableName;
    }
    
    /**
     * Obtener el nombre de la columna según el esquema actual
     * 
     * @param string $tableName Nombre de la tabla en inglés (convención Laravel)
     * @param string $columnName Nombre de la columna en inglés (convención Laravel)
     * @return string Nombre de la columna según el esquema actual
     */
    public function getColumnName($tableName, $columnName) {
        // Si estamos en el esquema de Laravel, devolver el nombre tal cual
        if ($this->schemaName === 'laravel') {
            return $columnName;
        }
        
        // Si estamos en otro esquema, buscar el nombre correspondiente
        $schemaTableName = $this->getTableName($tableName);
        
        if (isset($this->schema['tables'][$schemaTableName]['columns'][$columnName]['english'])) {
            return $this->schema['tables'][$schemaTableName]['columns'][$columnName]['english'];
        }
        
        // Si no se encuentra, devolver el nombre original
        return $columnName;
    }
    
    /**
     * Verificar si una tabla existe
     * 
     * @param string $tableName Nombre de la tabla en inglés (convención Laravel)
     * @return bool True si la tabla existe, false en caso contrario
     */
    public function tableExists($tableName) {
        $schemaTableName = $this->getTableName($tableName);
        
        // Consultar INFORMATION_SCHEMA para verificar si la tabla existe
        $query = "SELECT COUNT(*) FROM information_schema.tables 
                 WHERE table_schema = DATABASE() 
                 AND table_name = :tableName";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':tableName', $schemaTableName, PDO::PARAM_STR);
        $stmt->execute();
        
        $count = $stmt->fetchColumn();
        return $count > 0;
    }
    
    /**
     * Crear una tabla si no existe
     * 
     * @param string $tableName Nombre de la tabla en inglés (convención Laravel)
     * @return bool True si la tabla se creó, false si ya existía
     */
    public function createTableIfNotExists($tableName) {
        // Si la tabla ya existe, no hacer nada
        if ($this->tableExists($tableName)) {
            return false;
        }
        
        // Obtener la definición de la tabla en el esquema de Laravel
        if (!isset($this->schema['tables'][$tableName])) {
            throw new Exception("La tabla '$tableName' no está definida en el esquema actual");
        }
        
        $tableInfo = $this->schema['tables'][$tableName];
        $schemaTableName = $this->getTableName($tableName);
        
        // Construir la consulta SQL para crear la tabla
        $sql = "CREATE TABLE `$schemaTableName` (\n";
        
        $columns = [];
        $primaryKey = null;
        
        foreach ($tableInfo['columns'] as $columnName => $columnInfo) {
            // Saltar columnas de compatibilidad si estamos creando desde cero
            if (isset($columnInfo['compatibility']) && $columnInfo['compatibility'] === true) {
                continue;
            }
            
            $schemaColumnName = $this->getColumnName($tableName, $columnName);
            
            $column = "`$schemaColumnName` " . $columnInfo['type'];
            
            if (isset($columnInfo['auto_increment']) && $columnInfo['auto_increment']) {
                $column .= " AUTO_INCREMENT";
            }
            
            if (isset($columnInfo['primary']) && $columnInfo['primary']) {
                $primaryKey = $schemaColumnName;
            }
            
            $columns[] = $column;
        }
        
        // Agregar clave primaria
        if ($primaryKey !== null) {
            $columns[] = "PRIMARY KEY (`$primaryKey`)";
        }
        
        $sql .= implode(",\n", $columns);
        $sql .= "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        // Ejecutar la consulta
        try {
            $this->db->exec($sql);
            echo "Tabla `$schemaTableName` creada correctamente.\n";
            return true;
        } catch (Exception $e) {
            echo "Error al crear la tabla `$schemaTableName`: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Crear todas las tablas necesarias para la sincronización
     * 
     * @return int Número de tablas creadas
     */
    public function createAllTables() {
        $tablesCreated = 0;
        
        // Crear tablas en el orden correcto (para evitar problemas con claves foráneas)
        $tablesOrder = [
            'cities',
            'neighborhoods',
            'property_types',
            'property_uses',
            'property_states',
            'consignment_types',
            'advisors',
            'properties',
            'images',
            'property_changes',
            'property_states',
            'characteristics',
            'property_characteristics'
        ];
        
        foreach ($tablesOrder as $tableName) {
            if ($this->createTableIfNotExists($tableName)) {
                $tablesCreated++;
            }
        }
        
        return $tablesCreated;
    }
    
    /**
     * Verificar si todas las tablas necesarias existen
     * 
     * @param array $requiredTables Lista de tablas requeridas (en inglés, convención Laravel)
     * @return bool True si todas las tablas existen, false en caso contrario
     */
    public function checkRequiredTables($requiredTables) {
        foreach ($requiredTables as $tableName) {
            if (!$this->tableExists($tableName)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Crear las tablas necesarias si no existen
     * 
     * @param array $requiredTables Lista de tablas requeridas (en inglés, convención Laravel)
     * @return int Número de tablas creadas
     */
    public function createRequiredTables($requiredTables) {
        $tablesCreated = 0;
        
        foreach ($requiredTables as $tableName) {
            if ($this->createTableIfNotExists($tableName)) {
                $tablesCreated++;
            }
        }
        
        return $tablesCreated;
    }
}
