<?php
/**
 * Clase para gestionar el registro de eventos y mensajes
 */
class Logger {
    private $logFile;
    private $verbose;
    private $logDir;
    private $context;
    
    /**
     * Constructor
     * 
     * @param string $context Contexto del logger (ej: 'sync', 'reset', etc.)
     * @param bool $verbose Mostrar mensajes detallados en consola
     */
    public function __construct($context = 'app', $verbose = false) {
        $this->context = $context;
        $this->verbose = $verbose;
        $this->logDir = __DIR__ . '/../logs';
        
        // Crear directorio de logs si no existe
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
        
        // Crear archivo de log con fecha
        $date = date('Y-m-d');
        $this->logFile = $this->logDir . "/{$context}_{$date}.log";
    }
    
    /**
     * Registrar un mensaje informativo
     * 
     * @param string $message Mensaje a registrar
     */
    public function info($message) {
        $this->log('INFO', $message);
        echo $message . "\n";
    }
    
    /**
     * Registrar un mensaje de depuración
     * 
     * @param string $message Mensaje a registrar
     */
    public function debug($message) {
        $this->log('DEBUG', $message);
        
        if ($this->verbose) {
            echo "[DEBUG] " . $message . "\n";
        }
    }
    
    /**
     * Registrar un mensaje de advertencia
     * 
     * @param string $message Mensaje a registrar
     */
    public function warning($message) {
        $this->log('WARNING', $message);
        echo "[ADVERTENCIA] " . $message . "\n";
    }
    
    /**
     * Registrar un mensaje de error
     * 
     * @param string $message Mensaje a registrar
     */
    public function error($message) {
        $this->log('ERROR', $message);
        echo "[ERROR] " . $message . "\n";
    }
    
    /**
     * Registrar un mensaje en el archivo de log
     * 
     * @param string $level Nivel del mensaje
     * @param string $message Mensaje a registrar
     */
    private function log($level, $message) {
        $date = date('Y-m-d H:i:s');
        $logMessage = "[{$date}] [{$level}] [{$this->context}] {$message}" . PHP_EOL;
        
        // Escribir en archivo de log
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }
    
    /**
     * Obtener la ruta del archivo de log
     * 
     * @return string Ruta del archivo de log
     */
    public function getLogFile() {
        return $this->logFile;
    }
    
    /**
     * Establecer modo verbose
     * 
     * @param bool $verbose Modo verbose
     * @return self
     */
    public function setVerbose($verbose) {
        $this->verbose = (bool) $verbose;
        return $this;
    }
    
    /**
     * Limpiar logs antiguos
     * 
     * @param int $days Eliminar logs más antiguos que este número de días
     * @return int Número de archivos eliminados
     */
    public function cleanOldLogs($days = 30) {
        $count = 0;
        $cutoffTime = time() - ($days * 86400);
        
        $files = glob($this->logDir . '/*.log');
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $modTime = filemtime($file);
                if ($modTime < $cutoffTime) {
                    if (unlink($file)) {
                        $count++;
                    }
                }
            }
        }
        
        return $count;
    }
}
