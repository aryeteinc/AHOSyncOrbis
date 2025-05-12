<?php
/**
 * Clase para procesar datos en lotes para mejorar el rendimiento
 */
class BatchProcessor {
    private $db;
    private $logger;
    private $batchSize;
    private $totalProcessed = 0;
    private $successCount = 0;
    private $errorCount = 0;
    private $startTime;
    
    /**
     * Constructor
     * 
     * @param PDO $db Instancia de la base de datos
     * @param int $batchSize Tamaño del lote para procesamiento
     * @param callable $logger Función para registrar mensajes
     */
    public function __construct($db, $batchSize = 50, $logger = null) {
        $this->db = $db;
        $this->batchSize = $batchSize;
        $this->logger = $logger ?: function($message) {
            echo $message . "\n";
        };
        $this->startTime = microtime(true);
    }
    
    /**
     * Procesar un conjunto de datos en lotes
     * 
     * @param array $items Lista de elementos a procesar
     * @param callable $processor Función para procesar cada elemento
     * @param bool $useTransaction Usar transacciones para cada lote
     * @return array Estadísticas del procesamiento
     */
    public function processBatch($items, $processor, $useTransaction = true) {
        $this->totalProcessed = 0;
        $this->successCount = 0;
        $this->errorCount = 0;
        $this->startTime = microtime(true);
        
        $totalItems = count($items);
        $this->log("Iniciando procesamiento por lotes de {$totalItems} elementos (tamaño de lote: {$this->batchSize})");
        
        // Dividir elementos en lotes
        $batches = array_chunk($items, $this->batchSize);
        $batchCount = count($batches);
        
        $this->log("Dividido en {$batchCount} lotes");
        
        // Procesar cada lote
        foreach ($batches as $index => $batch) {
            $batchNumber = $index + 1;
            $this->log("Procesando lote {$batchNumber}/{$batchCount} (" . count($batch) . " elementos)");
            
            // Iniciar transacción si está habilitado
            if ($useTransaction) {
                $this->db->beginTransaction();
            }
            
            try {
                // Procesar cada elemento del lote
                foreach ($batch as $item) {
                    try {
                        $result = call_user_func($processor, $item);
                        $this->totalProcessed++;
                        
                        if ($result) {
                            $this->successCount++;
                        } else {
                            $this->errorCount++;
                        }
                    } catch (Exception $e) {
                        $this->errorCount++;
                        $this->log("Error al procesar elemento: " . $e->getMessage());
                    }
                }
                
                // Confirmar transacción si está habilitado
                if ($useTransaction) {
                    $this->db->commit();
                    $this->log("Lote {$batchNumber} completado y confirmado");
                }
            } catch (Exception $e) {
                // Revertir transacción en caso de error
                if ($useTransaction && $this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                
                $this->log("Error en lote {$batchNumber}: " . $e->getMessage());
                $this->errorCount += count($batch);
            }
            
            // Mostrar progreso
            $progress = round(($batchNumber / $batchCount) * 100, 2);
            $elapsed = round(microtime(true) - $this->startTime, 2);
            $this->log("Progreso: {$progress}% completado, {$this->totalProcessed}/{$totalItems} procesados, tiempo transcurrido: {$elapsed}s");
            
            // Liberar memoria
            gc_collect_cycles();
        }
        
        // Estadísticas finales
        $totalTime = round(microtime(true) - $this->startTime, 2);
        $itemsPerSecond = round($this->totalProcessed / $totalTime, 2);
        
        $this->log("Procesamiento por lotes completado en {$totalTime}s");
        $this->log("Elementos procesados: {$this->totalProcessed}, exitosos: {$this->successCount}, errores: {$this->errorCount}");
        $this->log("Rendimiento: {$itemsPerSecond} elementos/segundo");
        
        return [
            'total' => $this->totalProcessed,
            'success' => $this->successCount,
            'errors' => $this->errorCount,
            'time' => $totalTime,
            'performance' => $itemsPerSecond
        ];
    }
    
    /**
     * Procesar consultas SQL en lotes
     * 
     * @param string $query Consulta SQL para obtener los datos
     * @param callable $processor Función para procesar cada fila
     * @param array $queryParams Parámetros para la consulta
     * @param int $fetchSize Número de filas a obtener en cada lote
     * @return array Estadísticas del procesamiento
     */
    public function processSqlBatch($query, $processor, $queryParams = [], $fetchSize = 100) {
        $this->totalProcessed = 0;
        $this->successCount = 0;
        $this->errorCount = 0;
        $this->startTime = microtime(true);
        
        $this->log("Iniciando procesamiento SQL por lotes (tamaño de lote: {$fetchSize})");
        
        try {
            // Preparar la consulta
            $stmt = $this->db->prepare($query);
            $stmt->execute($queryParams);
            
            // Procesar resultados en lotes
            $batchNumber = 0;
            
            do {
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT, $fetchSize);
                
                if ($rows) {
                    $batchNumber++;
                    $rowCount = count($rows);
                    $this->log("Procesando lote SQL #{$batchNumber} ({$rowCount} filas)");
                    
                    // Iniciar transacción
                    $this->db->beginTransaction();
                    
                    try {
                        // Procesar cada fila del lote
                        foreach ($rows as $row) {
                            try {
                                $result = call_user_func($processor, $row);
                                $this->totalProcessed++;
                                
                                if ($result) {
                                    $this->successCount++;
                                } else {
                                    $this->errorCount++;
                                }
                            } catch (Exception $e) {
                                $this->errorCount++;
                                $this->log("Error al procesar fila: " . $e->getMessage());
                            }
                        }
                        
                        // Confirmar transacción
                        $this->db->commit();
                        $this->log("Lote SQL #{$batchNumber} completado y confirmado");
                    } catch (Exception $e) {
                        // Revertir transacción en caso de error
                        if ($this->db->inTransaction()) {
                            $this->db->rollBack();
                        }
                        
                        $this->log("Error en lote SQL #{$batchNumber}: " . $e->getMessage());
                        $this->errorCount += $rowCount;
                    }
                    
                    // Mostrar progreso
                    $elapsed = round(microtime(true) - $this->startTime, 2);
                    $this->log("Progreso: {$this->totalProcessed} filas procesadas, tiempo transcurrido: {$elapsed}s");
                    
                    // Liberar memoria
                    gc_collect_cycles();
                }
            } while ($rows);
            
            // Estadísticas finales
            $totalTime = round(microtime(true) - $this->startTime, 2);
            $itemsPerSecond = $totalTime > 0 ? round($this->totalProcessed / $totalTime, 2) : 0;
            
            $this->log("Procesamiento SQL por lotes completado en {$totalTime}s");
            $this->log("Filas procesadas: {$this->totalProcessed}, exitosas: {$this->successCount}, errores: {$this->errorCount}");
            $this->log("Rendimiento: {$itemsPerSecond} filas/segundo");
            
            return [
                'total' => $this->totalProcessed,
                'success' => $this->successCount,
                'errors' => $this->errorCount,
                'time' => $totalTime,
                'performance' => $itemsPerSecond
            ];
        } catch (PDOException $e) {
            $this->log("Error al ejecutar consulta SQL: " . $e->getMessage());
            return [
                'total' => 0,
                'success' => 0,
                'errors' => 1,
                'time' => round(microtime(true) - $this->startTime, 2),
                'performance' => 0
            ];
        }
    }
    
    /**
     * Insertar múltiples registros en una sola operación
     * 
     * @param string $table Nombre de la tabla
     * @param array $columns Columnas a insertar
     * @param array $valuesList Lista de valores para cada registro
     * @param int $chunkSize Tamaño del lote para dividir inserciones grandes
     * @return array Estadísticas de la inserción
     */
    public function bulkInsert($table, $columns, $valuesList, $chunkSize = 100) {
        $this->startTime = microtime(true);
        $totalRecords = count($valuesList);
        
        $this->log("Iniciando inserción masiva de {$totalRecords} registros en tabla {$table}");
        
        if (empty($valuesList)) {
            $this->log("No hay registros para insertar");
            return [
                'total' => 0,
                'success' => 0,
                'errors' => 0,
                'time' => 0,
                'performance' => 0
            ];
        }
        
        // Dividir en lotes más pequeños para evitar problemas con consultas muy grandes
        $chunks = array_chunk($valuesList, $chunkSize);
        $totalChunks = count($chunks);
        $this->log("Dividido en {$totalChunks} lotes de máximo {$chunkSize} registros cada uno");
        
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($chunks as $index => $chunk) {
            $chunkNumber = $index + 1;
            $chunkSize = count($chunk);
            
            $this->log("Procesando lote {$chunkNumber}/{$totalChunks} ({$chunkSize} registros)");
            
            try {
                // Construir la consulta SQL para inserción múltiple
                $placeholders = [];
                $flatValues = [];
                
                foreach ($chunk as $values) {
                    $rowPlaceholders = [];
                    
                    foreach ($values as $value) {
                        $rowPlaceholders[] = '?';
                        $flatValues[] = $value;
                    }
                    
                    $placeholders[] = '(' . implode(', ', $rowPlaceholders) . ')';
                }
                
                $sql = "INSERT INTO {$table} (" . implode(', ', $columns) . ") VALUES " . implode(', ', $placeholders);
                
                // Ejecutar la consulta
                $stmt = $this->db->prepare($sql);
                $result = $stmt->execute($flatValues);
                
                if ($result) {
                    $affected = $stmt->rowCount();
                    $successCount += $affected;
                    $this->log("Lote {$chunkNumber} completado: {$affected} registros insertados");
                } else {
                    $errorCount += $chunkSize;
                    $this->log("Error al insertar lote {$chunkNumber}");
                }
            } catch (PDOException $e) {
                $errorCount += $chunkSize;
                $this->log("Error en lote {$chunkNumber}: " . $e->getMessage());
            }
            
            // Mostrar progreso
            $progress = round(($chunkNumber / $totalChunks) * 100, 2);
            $elapsed = round(microtime(true) - $this->startTime, 2);
            $this->log("Progreso: {$progress}% completado, tiempo transcurrido: {$elapsed}s");
        }
        
        // Estadísticas finales
        $totalTime = round(microtime(true) - $this->startTime, 2);
        $recordsPerSecond = $totalTime > 0 ? round($successCount / $totalTime, 2) : 0;
        
        $this->log("Inserción masiva completada en {$totalTime}s");
        $this->log("Registros insertados: {$successCount}, errores: {$errorCount}");
        $this->log("Rendimiento: {$recordsPerSecond} registros/segundo");
        
        return [
            'total' => $totalRecords,
            'success' => $successCount,
            'errors' => $errorCount,
            'time' => $totalTime,
            'performance' => $recordsPerSecond
        ];
    }
    
    /**
     * Actualizar múltiples registros en lotes
     * 
     * @param string $table Nombre de la tabla
     * @param array $data Lista de datos para actualizar [id => [campo => valor, ...], ...]
     * @param string $idColumn Nombre de la columna de ID
     * @return array Estadísticas de la actualización
     */
    public function bulkUpdate($table, $data, $idColumn = 'id') {
        $this->startTime = microtime(true);
        $totalRecords = count($data);
        
        $this->log("Iniciando actualización masiva de {$totalRecords} registros en tabla {$table}");
        
        if (empty($data)) {
            $this->log("No hay registros para actualizar");
            return [
                'total' => 0,
                'success' => 0,
                'errors' => 0,
                'time' => 0,
                'performance' => 0
            ];
        }
        
        // Dividir en lotes
        $batches = array_chunk(array_keys($data), $this->batchSize, true);
        $batchCount = count($batches);
        
        $this->log("Dividido en {$batchCount} lotes");
        
        $successCount = 0;
        $errorCount = 0;
        
        // Procesar cada lote
        foreach ($batches as $index => $batchIds) {
            $batchNumber = $index + 1;
            $batchSize = count($batchIds);
            
            $this->log("Procesando lote {$batchNumber}/{$batchCount} ({$batchSize} registros)");
            
            // Iniciar transacción
            $this->db->beginTransaction();
            
            try {
                $batchSuccess = 0;
                
                // Actualizar cada registro del lote
                foreach ($batchIds as $id) {
                    $record = $data[$id];
                    
                    if (empty($record)) {
                        continue;
                    }
                    
                    // Construir la consulta de actualización
                    $setClauses = [];
                    $params = [];
                    
                    foreach ($record as $field => $value) {
                        $setClauses[] = "{$field} = ?";
                        $params[] = $value;
                    }
                    
                    // Añadir el ID al final de los parámetros
                    $params[] = $id;
                    
                    $sql = "UPDATE {$table} SET " . implode(', ', $setClauses) . " WHERE {$idColumn} = ?";
                    
                    // Ejecutar la actualización
                    $stmt = $this->db->prepare($sql);
                    $result = $stmt->execute($params);
                    
                    if ($result) {
                        $batchSuccess++;
                    } else {
                        $errorCount++;
                    }
                }
                
                // Confirmar transacción
                $this->db->commit();
                $successCount += $batchSuccess;
                $this->log("Lote {$batchNumber} completado: {$batchSuccess} registros actualizados");
            } catch (PDOException $e) {
                // Revertir transacción en caso de error
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                
                $errorCount += $batchSize;
                $this->log("Error en lote {$batchNumber}: " . $e->getMessage());
            }
            
            // Mostrar progreso
            $progress = round(($batchNumber / $batchCount) * 100, 2);
            $elapsed = round(microtime(true) - $this->startTime, 2);
            $this->log("Progreso: {$progress}% completado, tiempo transcurrido: {$elapsed}s");
            
            // Liberar memoria
            gc_collect_cycles();
        }
        
        // Estadísticas finales
        $totalTime = round(microtime(true) - $this->startTime, 2);
        $recordsPerSecond = $totalTime > 0 ? round($successCount / $totalTime, 2) : 0;
        
        $this->log("Actualización masiva completada en {$totalTime}s");
        $this->log("Registros actualizados: {$successCount}, errores: {$errorCount}");
        $this->log("Rendimiento: {$recordsPerSecond} registros/segundo");
        
        return [
            'total' => $totalRecords,
            'success' => $successCount,
            'errors' => $errorCount,
            'time' => $totalTime,
            'performance' => $recordsPerSecond
        ];
    }
    
    /**
     * Establecer el tamaño del lote
     * 
     * @param int $size Nuevo tamaño del lote
     * @return self
     */
    public function setBatchSize($size) {
        $this->batchSize = max(1, (int) $size);
        return $this;
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
