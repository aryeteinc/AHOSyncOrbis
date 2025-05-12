<?php
/**
 * Clase principal para la sincronización de datos inmobiliarios
 */
class Synchronizer {
    private $db;
    private $apiUrl;
    private $apiKey;
    private $imagesFolder;
    private $downloadImages;
    private $trackChanges;
    private $stats;
    private $startTime;
    
    /**
     * Constructor
     * 
     * @param PDO $db Instancia de la base de datos
     * @param string $apiUrl URL de la API
     * @param string $apiKey Clave de la API
     * @param string $imagesFolder Carpeta para las imágenes
     * @param bool $downloadImages Si se deben descargar imágenes
     * @param bool $trackChanges Si se deben registrar cambios
     */
    public function __construct($db, $apiUrl, $apiKey, $imagesFolder, $downloadImages = true, $trackChanges = true) {
        $this->db = $db;
        $this->apiUrl = $apiUrl;
        $this->apiKey = $apiKey;
        $this->imagesFolder = $imagesFolder;
        $this->downloadImages = $downloadImages;
        $this->trackChanges = $trackChanges;
        $this->startTime = microtime(true);
        
        // Inicializar estadísticas
        $this->stats = [
            'properties_processed' => 0,
            'properties_new' => 0,
            'properties_updated' => 0,
            'properties_unchanged' => 0,
            'images_downloaded' => 0,
            'images_deleted' => 0,
            'errors' => 0,
            'start' => date('Y-m-d H:i:s'),
            'end' => null,
            'duration' => 0
        ];
        
        // Crear carpeta de imágenes si no existe
        if (!is_dir($this->imagesFolder)) {
            mkdir($this->imagesFolder, 0755, true);
        }
        
        // Crear carpeta de logs si no existe
        if (!is_dir(dirname(__DIR__) . '/logs')) {
            mkdir(dirname(__DIR__) . '/logs', 0755, true);
        }
    }
    
    /**
     * Sincronizar datos
     * 
     * @param int $limit Límite de propiedades a sincronizar (0 = sin límite)
     * @param bool $force Forzar sincronización completa
     * @param string|null $specificRef Referencia específica a sincronizar
     * @return array Estadísticas de sincronización
     */
    public function synchronize($limit = 0, $force = false, $specificRef = null) {
        echo "\n" . str_repeat('=', 70) . "\n";
        echo "SINCRONIZACIÓN DE DATOS INMOBILIARIOS (PHP)\n";
        echo str_repeat('=', 70) . "\n\n";
        
        echo "Iniciando sincronización...\n";
        $this->logMessage("Iniciando sincronización");
        
        try {
            // Verificar tablas necesarias
            $this->checkRequiredTables();
            
            // Verificar y crear carpeta de imágenes
            $this->setupImageFolders();
            
            // Obtener datos de la API
            $properties = $this->fetchDataFromApi($limit);
            
            if (empty($properties)) {
                echo "No se encontraron propiedades para sincronizar\n";
                $this->logMessage("No se encontraron propiedades para sincronizar");
                return $this->stats;
            }
            
            // Filtrar por referencia específica si se proporciona
            if ($specificRef) {
                $filteredProperties = [];
                foreach ($properties as $property) {
                    if ($property['ref'] === $specificRef) {
                        $filteredProperties[] = $property;
                        break; // Solo necesitamos una coincidencia
                    }
                }
                
                if (empty($filteredProperties)) {
                    echo "No se encontró ninguna propiedad con la referencia: {$specificRef}\n";
                    $this->logMessage("No se encontró ninguna propiedad con la referencia: {$specificRef}");
                    return $this->stats;
                }
                
                $properties = $filteredProperties;
                echo "Se filtrará solo la propiedad con referencia: {$specificRef}\n";
                $this->logMessage("Se filtrará solo la propiedad con referencia: {$specificRef}");
            }
            
            echo "Se procesarán " . count($properties) . " propiedades\n";
            $this->logMessage("Se procesarán " . count($properties) . " propiedades");
            
            // Crear procesador de propiedades
            $propertyProcessor = new PropertyProcessor(
                $this->db,
                $this->imagesFolder,
                $this->downloadImages,
                $this->trackChanges,
                $this->stats
            );
            
            // Procesar cada propiedad
            $count = 0;
            $total = count($properties);
            
            foreach ($properties as $property) {
                $count++;
                $progressPercent = round(($count / $total) * 100);
                echo "\nProcesando inmueble #{$count}/{$total} ({$progressPercent}%): Ref {$property['ref']}\n";
                
                try {
                    $propertyProcessor->processProperty($property);
                } catch (Exception $e) {
                    echo "Error al procesar propiedad: " . $e->getMessage() . "\n";
                    $this->logMessage("Error al procesar propiedad {$property['ref']}: " . $e->getMessage());
                    $this->stats['errors']++;
                }
            }
            
            // Calcular duración
            $this->stats['end'] = date('Y-m-d H:i:s');
            $this->stats['duration'] = round(microtime(true) - $this->startTime, 2);
            
            // Mostrar resumen
            $this->showSummary();
            
            return $this->stats;
        } catch (Exception $e) {
            echo "Error fatal durante la sincronización: " . $e->getMessage() . "\n";
            $this->logMessage("Error fatal: " . $e->getMessage());
            $this->stats['errors']++;
            return $this->stats;
        }
    }
    
    /**
     * Verificar que existan las tablas necesarias
     */
    private function checkRequiredTables() {
        $requiredTables = ['properties', 'images'];
        $missingTables = [];
        
        echo "Verificando tablas necesarias...\n";
        
        foreach ($requiredTables as $table) {
            $stmt = $this->db->query("SHOW TABLES LIKE '{$table}'");
            
            if ($stmt->rowCount() === 0) {
                $missingTables[] = $table;
                echo "La tabla '{$table}' no existe en la base de datos\n";
            } else {
                echo "Tabla '{$table}' encontrada\n";
            }
        }
        
        if (!empty($missingTables)) {
            echo "\nFaltan las siguientes tablas: " . implode(', ', $missingTables) . "\n";
            echo "Ejecute 'php commands/reset.php --confirm' para crear todas las tablas\n";
            throw new Exception("Faltan tablas requeridas en la base de datos");
        }
        
        echo "Todas las tablas necesarias existen\n";
    }
    
    /**
     * Configurar carpetas de imágenes
     */
    private function setupImageFolders() {
        echo "Verificando carpeta de imágenes...\n";
        
        // Verificar carpeta principal de imágenes
        if (!is_dir($this->imagesFolder)) {
            echo "Creando carpeta de imágenes: {$this->imagesFolder}\n";
            if (!mkdir($this->imagesFolder, 0755, true)) {
                throw new Exception("No se pudo crear la carpeta de imágenes: {$this->imagesFolder}");
            }
        }
        
        // Verificar permisos de escritura
        if (!is_writable($this->imagesFolder)) {
            throw new Exception("La carpeta de imágenes no tiene permisos de escritura: {$this->imagesFolder}");
        }
        
        echo "Carpeta de imágenes configurada correctamente: {$this->imagesFolder}\n";
    }
    
    /**
     * Mostrar resumen de la sincronización
     */
    private function showSummary() {
        echo "\n" . str_repeat('=', 70) . "\n";
        echo "RESUMEN DE SINCRONIZACIÓN\n";
        echo str_repeat('=', 70) . "\n";
        echo "Inicio: {$this->stats['start']}\n";
        echo "Fin: {$this->stats['end']}\n";
        echo "Duración: {$this->stats['duration']} segundos\n\n";
        echo "Inmuebles procesados: {$this->stats['properties_processed']}\n";
        echo "Inmuebles nuevos: {$this->stats['properties_new']}\n";
        echo "Inmuebles actualizados: {$this->stats['properties_updated']}\n";
        echo "Inmuebles sin cambios: {$this->stats['properties_unchanged']}\n";
        echo "Imágenes descargadas: {$this->stats['images_downloaded']}\n";
        echo "Imágenes eliminadas: {$this->stats['images_deleted']}\n";
        echo "Errores: {$this->stats['errors']}\n";
        echo str_repeat('=', 70) . "\n";
        
        // Guardar estadísticas en archivo de log
        $this->logMessage("Sincronización completada. Estadísticas: " . json_encode($this->stats));
    }
    
    /**
     * Registrar mensaje en el archivo de log
     * 
     * @param string $message Mensaje a registrar
     */
    private function logMessage($message) {
        $logFile = dirname(__DIR__) . '/logs/sync_' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}\n";
        
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
    
    /**
     * Obtener datos de la API
     * 
     * @param int $limit Límite de propiedades a obtener
     * @return array Datos de propiedades
     */
    private function fetchDataFromApi($limit = 0) {
        echo "Obteniendo datos de la API: {$this->apiUrl}\n";
        
        // Usar la URL de la API tal como está deendida
        $url = $this->apiUrl;
        
        // Solo añadir la clave API si es necesaria y no está vacía
        if (!empty($this->apiKey)) {
            $url .= (strpos($url, '?') === false) ? '?' : '&';
            $url .= "api_key=" . urlencode($this->apiKey);
        }
        
        // No aplicamos ningún filtro en la API, todos los filtros se aplican localmente
        
        // Realizar petición a la API
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: SyncOrbisPhp/1.0',
                    'Accept: application/json'
                ],
                'timeout' => 30
            ]
        ]);
        
        try {
            $response = file_get_contents($url, false, $context);
            
            if ($response === false) {
                throw new Exception("Error al obtener datos de la API");
            }
            
            $data = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Error al decodificar respuesta JSON: " . json_last_error_msg());
            }
            
            // Verificar que la respuesta sea un array (la API devuelve directamente un array de propiedades)
            if (!is_array($data)) {
                throw new Exception("Formato de respuesta inválido: se esperaba un array de propiedades");
            }
            
            // Obtener las propiedades de la respuesta
            $properties = null;
            
            // Si la respuesta tiene un campo 'data', usarlo como fuente de propiedades
            if (isset($data['data']) && is_array($data['data'])) {
                $properties = $data['data'];
            }
            // Si la respuesta tiene un campo 'inmuebles', usarlo como fuente de propiedades
            else if (isset($data['inmuebles']) && is_array($data['inmuebles'])) {
                $properties = $data['inmuebles'];
            }
            // Si la respuesta es directamente un array de propiedades, usarlo tal cual
            else if (isset($data[0])) {
                $properties = $data;
            }
            
            if (!$properties) {
                throw new Exception("Formato de respuesta inválido: no se encontraron propiedades");
            }
            
            // Aplicar el límite en el lado del cliente
            if ($limit > 0 && count($properties) > $limit) {
                echo "Limitando a {$limit} propiedades de " . count($properties) . " disponibles\n";
                $properties = array_slice($properties, 0, $limit);
            }
            
            echo "Se procesarán " . count($properties) . " propiedades\n";
            return $properties;
        } catch (Exception $e) {
            echo "Error al obtener datos de la API: " . $e->getMessage() . "\n";
            $this->logMessage("Error al obtener datos de la API: " . $e->getMessage());
            $this->stats['errors']++;
            return [];
        }
    }
}
