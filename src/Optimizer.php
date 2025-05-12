<?php
/**
 * Clase para optimizar el rendimiento de la sincronización
 */
class Optimizer {
    private $db;
    private $logger;
    
    /**
     * Constructor
     * 
     * @param PDO $db Instancia de la base de datos
     * @param callable $logger Función para registrar mensajes
     */
    public function __construct($db, $logger = null) {
        $this->db = $db;
        $this->logger = $logger ?: function($message) {
            echo $message . "\n";
        };
    }
    
    /**
     * Optimizar la base de datos
     * 
     * @return bool Resultado de la optimización
     */
    public function optimizeDatabase() {
        $this->log("Optimizando base de datos...");
        
        try {
            // Verificar si estamos usando MySQL
            $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver !== 'mysql') {
                $this->log("La optimización de tablas solo está disponible para MySQL");
                return false;
            }
            
            // Obtener todas las tablas
            $stmt = $this->db->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (empty($tables)) {
                $this->log("No se encontraron tablas para optimizar");
                return false;
            }
            
            $this->log("Encontradas " . count($tables) . " tablas para optimizar");
            
            // Optimizar cada tabla
            foreach ($tables as $table) {
                $this->log("Optimizando tabla: {$table}");
                
                // Analizar tabla
                $this->db->exec("ANALYZE TABLE `{$table}`");
                
                // Optimizar tabla
                $this->db->exec("OPTIMIZE TABLE `{$table}`");
            }
            
            $this->log("Optimización de tablas completada");
            return true;
        } catch (PDOException $e) {
            $this->log("Error al optimizar la base de datos: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Crear índices para mejorar el rendimiento
     * 
     * @return bool Resultado de la creación de índices
     */
    public function createIndexes() {
        $this->log("Creando índices para mejorar el rendimiento...");
        
        try {
            // Verificar si la tabla inmuebles existe
            $stmt = $this->db->query("SHOW TABLES LIKE 'inmuebles'");
            if ($stmt->rowCount() === 0) {
                $this->log("La tabla inmuebles no existe");
                return false;
            }
            
            // Verificar y crear índices importantes
            $indexes = [
                'idx_inmuebles_ref' => "CREATE INDEX IF NOT EXISTS idx_inmuebles_ref ON inmuebles (ref)",
                'idx_inmuebles_slug' => "CREATE INDEX IF NOT EXISTS idx_inmuebles_slug ON inmuebles (slug)",
                'idx_inmuebles_hash' => "CREATE INDEX IF NOT EXISTS idx_inmuebles_hash_datos ON inmuebles (hash_datos)",
                'idx_inmuebles_ciudad' => "CREATE INDEX IF NOT EXISTS idx_inmuebles_ciudad ON inmuebles (ciudad_id)",
                'idx_inmuebles_tipo' => "CREATE INDEX IF NOT EXISTS idx_inmuebles_tipo ON inmuebles (tipo_inmueble_id)",
                'idx_inmuebles_uso' => "CREATE INDEX IF NOT EXISTS idx_inmuebles_uso ON inmuebles (uso_inmueble_id)",
                'idx_inmuebles_estado' => "CREATE INDEX IF NOT EXISTS idx_inmuebles_estado ON inmuebles (estado_inmueble_id)",
                'idx_inmuebles_precio_venta' => "CREATE INDEX IF NOT EXISTS idx_inmuebles_precio_venta ON inmuebles (precio_venta)",
                'idx_inmuebles_precio_arriendo' => "CREATE INDEX IF NOT EXISTS idx_inmuebles_precio_arriendo ON inmuebles (precio_arriendo)",
                'idx_inmuebles_destacado' => "CREATE INDEX IF NOT EXISTS idx_inmuebles_destacado ON inmuebles (destacado)",
                'idx_inmuebles_created' => "CREATE INDEX IF NOT EXISTS idx_inmuebles_created ON inmuebles (created_at)",
                'idx_inmuebles_updated' => "CREATE INDEX IF NOT EXISTS idx_inmuebles_updated ON inmuebles (updated_at)"
            ];
            
            // Verificar y crear índices para la tabla imagenes
            $stmtImagenes = $this->db->query("SHOW TABLES LIKE 'imagenes'");
            if ($stmtImagenes->rowCount() > 0) {
                $indexes['idx_imagenes_inmueble'] = "CREATE INDEX IF NOT EXISTS idx_imagenes_inmueble ON imagenes (inmueble_id)";
                $indexes['idx_imagenes_hash'] = "CREATE INDEX IF NOT EXISTS idx_imagenes_hash ON imagenes (hash)";
                $indexes['idx_imagenes_orden'] = "CREATE INDEX IF NOT EXISTS idx_imagenes_orden ON imagenes (orden)";
            }
            
            // Crear cada índice
            $createdIndexes = 0;
            foreach ($indexes as $name => $sql) {
                try {
                    $this->db->exec($sql);
                    $this->log("Índice creado: {$name}");
                    $createdIndexes++;
                } catch (PDOException $e) {
                    // Si el índice ya existe o hay otro error, intentar una sintaxis alternativa
                    if (strpos($e->getMessage(), "already exists") !== false) {
                        $this->log("El índice {$name} ya existe");
                    } else {
                        // Intentar sintaxis alternativa para MySQL 5.7
                        try {
                            $altSql = str_replace("IF NOT EXISTS ", "", $sql);
                            $this->db->exec($altSql);
                            $this->log("Índice creado (sintaxis alternativa): {$name}");
                            $createdIndexes++;
                        } catch (PDOException $e2) {
                            $this->log("Error al crear índice {$name}: " . $e2->getMessage());
                        }
                    }
                }
            }
            
            $this->log("Creación de índices completada. Creados {$createdIndexes} de " . count($indexes) . " índices");
            return $createdIndexes > 0;
        } catch (PDOException $e) {
            $this->log("Error al crear índices: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Optimizar la configuración de PHP para mejorar el rendimiento
     * 
     * @return array Configuración optimizada
     */
    public function optimizePhpSettings() {
        $this->log("Optimizando configuración de PHP...");
        
        $originalSettings = [
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'default_socket_timeout' => ini_get('default_socket_timeout')
        ];
        
        // Aumentar el límite de memoria si es necesario
        $memoryLimit = ini_get('memory_limit');
        $memoryValue = (int) $memoryLimit;
        if (strpos($memoryLimit, 'M') !== false && $memoryValue < 256) {
            ini_set('memory_limit', '256M');
            $this->log("Límite de memoria aumentado de {$memoryLimit} a 256M");
        }
        
        // Aumentar el tiempo máximo de ejecución
        $maxExecutionTime = ini_get('max_execution_time');
        if ($maxExecutionTime < 300 && $maxExecutionTime != 0) {
            ini_set('max_execution_time', '300');
            $this->log("Tiempo máximo de ejecución aumentado de {$maxExecutionTime}s a 300s");
        }
        
        // Aumentar el tiempo de espera para sockets
        $socketTimeout = ini_get('default_socket_timeout');
        if ($socketTimeout < 60) {
            ini_set('default_socket_timeout', '60');
            $this->log("Tiempo de espera para sockets aumentado de {$socketTimeout}s a 60s");
        }
        
        $newSettings = [
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'default_socket_timeout' => ini_get('default_socket_timeout')
        ];
        
        $this->log("Configuración de PHP optimizada");
        return [
            'original' => $originalSettings,
            'new' => $newSettings
        ];
    }
    
    /**
     * Optimizar la estructura de la base de datos
     * 
     * @return bool Resultado de la optimización
     */
    public function optimizeTableStructure() {
        $this->log("Optimizando estructura de tablas...");
        
        try {
            // Verificar si la tabla inmuebles existe
            $stmt = $this->db->query("SHOW TABLES LIKE 'inmuebles'");
            if ($stmt->rowCount() === 0) {
                $this->log("La tabla inmuebles no existe");
                return false;
            }
            
            // Obtener la estructura actual de la tabla
            $columns = $this->db->query("SHOW COLUMNS FROM inmuebles")->fetchAll(PDO::FETCH_ASSOC);
            $columnTypes = [];
            
            foreach ($columns as $column) {
                $columnTypes[$column['Field']] = [
                    'type' => $column['Type'],
                    'null' => $column['Null'],
                    'key' => $column['Key'],
                    'default' => $column['Default'],
                    'extra' => $column['Extra']
                ];
            }
            
            // Optimizar tipos de datos para mejorar el rendimiento
            $optimizations = [];
            
            // Verificar y optimizar tipos de datos numéricos
            if (isset($columnTypes['precio_venta']) && strpos($columnTypes['precio_venta']['type'], 'decimal') === 0) {
                // Verificar si la precisión es adecuada
                if ($columnTypes['precio_venta']['type'] !== 'decimal(12,2)') {
                    $optimizations[] = "ALTER TABLE inmuebles MODIFY COLUMN precio_venta DECIMAL(12,2) DEFAULT 0";
                }
            }
            
            if (isset($columnTypes['precio_arriendo']) && strpos($columnTypes['precio_arriendo']['type'], 'decimal') === 0) {
                if ($columnTypes['precio_arriendo']['type'] !== 'decimal(12,2)') {
                    $optimizations[] = "ALTER TABLE inmuebles MODIFY COLUMN precio_arriendo DECIMAL(12,2) DEFAULT 0";
                }
            }
            
            // Verificar y optimizar tipos de texto
            if (isset($columnTypes['ref']) && $columnTypes['ref']['type'] !== 'varchar(50)') {
                $optimizations[] = "ALTER TABLE inmuebles MODIFY COLUMN ref VARCHAR(50) NOT NULL";
            }
            
            if (isset($columnTypes['titulo']) && $columnTypes['titulo']['type'] !== 'varchar(255)') {
                $optimizations[] = "ALTER TABLE inmuebles MODIFY COLUMN titulo VARCHAR(255) NOT NULL";
            }
            
            // Ejecutar optimizaciones
            if (!empty($optimizations)) {
                $this->log("Aplicando " . count($optimizations) . " optimizaciones a la estructura de la tabla inmuebles");
                
                foreach ($optimizations as $sql) {
                    try {
                        $this->db->exec($sql);
                        $this->log("Optimización aplicada: " . $sql);
                    } catch (PDOException $e) {
                        $this->log("Error al aplicar optimización: " . $e->getMessage());
                    }
                }
            } else {
                $this->log("La estructura de la tabla inmuebles ya está optimizada");
            }
            
            return true;
        } catch (PDOException $e) {
            $this->log("Error al optimizar la estructura de tablas: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Registrar un mensaje
     * 
     * @param string $message Mensaje a registrar
     */
    private function log($message) {
        call_user_func($this->logger, $message);
    }
}
