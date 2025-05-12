<?php
/**
 * Clase para gestionar el caché y mejorar el rendimiento
 */
class CacheManager {
    private $cacheDir;
    private $enabled;
    private $ttl;
    private $logger;
    
    /**
     * Constructor
     * 
     * @param string $cacheDir Directorio para almacenar archivos de caché
     * @param bool $enabled Habilitar o deshabilitar el caché
     * @param int $ttl Tiempo de vida del caché en segundos (0 = sin expiración)
     * @param callable $logger Función para registrar mensajes
     */
    public function __construct($cacheDir = null, $enabled = true, $ttl = 3600, $logger = null) {
        $this->cacheDir = $cacheDir ?: __DIR__ . '/../cache';
        $this->enabled = $enabled;
        $this->ttl = $ttl;
        $this->logger = $logger ?: function($message) {
            echo $message . "\n";
        };
        
        // Crear directorio de caché si no existe
        if ($this->enabled && !is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
            $this->log("Directorio de caché creado: {$this->cacheDir}");
        }
    }
    
    /**
     * Obtener un elemento del caché
     * 
     * @param string $key Clave del elemento
     * @param mixed $default Valor por defecto si no existe
     * @return mixed Valor almacenado o valor por defecto
     */
    public function get($key, $default = null) {
        if (!$this->enabled) {
            return $default;
        }
        
        $cacheFile = $this->getCacheFilePath($key);
        
        if (!file_exists($cacheFile)) {
            return $default;
        }
        
        // Verificar si el caché ha expirado
        if ($this->ttl > 0) {
            $modTime = filemtime($cacheFile);
            if (time() - $modTime > $this->ttl) {
                $this->log("Caché expirado para clave: {$key}");
                @unlink($cacheFile);
                return $default;
            }
        }
        
        // Leer datos del caché
        $data = @file_get_contents($cacheFile);
        if ($data === false) {
            $this->log("Error al leer archivo de caché para clave: {$key}");
            return $default;
        }
        
        // Deserializar datos
        $value = @unserialize($data);
        if ($value === false && $data !== serialize(false)) {
            $this->log("Error al deserializar datos de caché para clave: {$key}");
            return $default;
        }
        
        $this->log("Caché recuperado para clave: {$key}");
        return $value;
    }
    
    /**
     * Almacenar un elemento en el caché
     * 
     * @param string $key Clave del elemento
     * @param mixed $value Valor a almacenar
     * @param int $ttl Tiempo de vida específico (0 = usar valor por defecto)
     * @return bool Resultado de la operación
     */
    public function set($key, $value, $ttl = 0) {
        if (!$this->enabled) {
            return false;
        }
        
        $cacheFile = $this->getCacheFilePath($key);
        
        // Serializar datos
        $data = serialize($value);
        
        // Escribir en archivo de caché
        $result = @file_put_contents($cacheFile, $data, LOCK_EX);
        
        if ($result === false) {
            $this->log("Error al escribir en archivo de caché para clave: {$key}");
            return false;
        }
        
        $this->log("Caché almacenado para clave: {$key}");
        return true;
    }
    
    /**
     * Verificar si un elemento existe en el caché
     * 
     * @param string $key Clave del elemento
     * @return bool Verdadero si existe y no ha expirado
     */
    public function has($key) {
        if (!$this->enabled) {
            return false;
        }
        
        $cacheFile = $this->getCacheFilePath($key);
        
        if (!file_exists($cacheFile)) {
            return false;
        }
        
        // Verificar si el caché ha expirado
        if ($this->ttl > 0) {
            $modTime = filemtime($cacheFile);
            if (time() - $modTime > $this->ttl) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Eliminar un elemento del caché
     * 
     * @param string $key Clave del elemento
     * @return bool Resultado de la operación
     */
    public function delete($key) {
        if (!$this->enabled) {
            return false;
        }
        
        $cacheFile = $this->getCacheFilePath($key);
        
        if (!file_exists($cacheFile)) {
            return true;
        }
        
        $result = @unlink($cacheFile);
        
        if ($result) {
            $this->log("Caché eliminado para clave: {$key}");
        } else {
            $this->log("Error al eliminar caché para clave: {$key}");
        }
        
        return $result;
    }
    
    /**
     * Limpiar todo el caché
     * 
     * @return bool Resultado de la operación
     */
    public function clear() {
        if (!$this->enabled || !is_dir($this->cacheDir)) {
            return false;
        }
        
        $files = glob($this->cacheDir . '/*.cache');
        $count = 0;
        
        foreach ($files as $file) {
            if (is_file($file) && @unlink($file)) {
                $count++;
            }
        }
        
        $this->log("Caché limpiado: {$count} archivos eliminados");
        return true;
    }
    
    /**
     * Limpiar caché expirado
     * 
     * @return int Número de archivos eliminados
     */
    public function clearExpired() {
        if (!$this->enabled || !is_dir($this->cacheDir) || $this->ttl <= 0) {
            return 0;
        }
        
        $files = glob($this->cacheDir . '/*.cache');
        $count = 0;
        $now = time();
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $modTime = filemtime($file);
                if ($now - $modTime > $this->ttl) {
                    if (@unlink($file)) {
                        $count++;
                    }
                }
            }
        }
        
        $this->log("Caché expirado limpiado: {$count} archivos eliminados");
        return $count;
    }
    
    /**
     * Obtener estadísticas del caché
     * 
     * @return array Estadísticas del caché
     */
    public function getStats() {
        if (!$this->enabled || !is_dir($this->cacheDir)) {
            return [
                'enabled' => $this->enabled,
                'total_files' => 0,
                'total_size' => 0,
                'expired_files' => 0,
                'directory' => $this->cacheDir,
                'ttl' => $this->ttl
            ];
        }
        
        $files = glob($this->cacheDir . '/*.cache');
        $totalFiles = count($files);
        $totalSize = 0;
        $expiredFiles = 0;
        $now = time();
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $totalSize += filesize($file);
                
                if ($this->ttl > 0) {
                    $modTime = filemtime($file);
                    if ($now - $modTime > $this->ttl) {
                        $expiredFiles++;
                    }
                }
            }
        }
        
        return [
            'enabled' => $this->enabled,
            'total_files' => $totalFiles,
            'total_size' => $totalSize,
            'expired_files' => $expiredFiles,
            'directory' => $this->cacheDir,
            'ttl' => $this->ttl
        ];
    }
    
    /**
     * Obtener la ruta del archivo de caché para una clave
     * 
     * @param string $key Clave del elemento
     * @return string Ruta del archivo de caché
     */
    private function getCacheFilePath($key) {
        $safeKey = preg_replace('/[^a-zA-Z0-9_]/', '_', $key);
        $hash = md5($key);
        return $this->cacheDir . '/' . $safeKey . '_' . $hash . '.cache';
    }
    
    /**
     * Habilitar o deshabilitar el caché
     * 
     * @param bool $enabled Estado del caché
     * @return self
     */
    public function setEnabled($enabled) {
        $this->enabled = (bool) $enabled;
        return $this;
    }
    
    /**
     * Establecer el tiempo de vida del caché
     * 
     * @param int $ttl Tiempo de vida en segundos
     * @return self
     */
    public function setTtl($ttl) {
        $this->ttl = max(0, (int) $ttl);
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
    
    /**
     * Obtener o almacenar un valor en caché
     * 
     * @param string $key Clave del elemento
     * @param callable $callback Función para generar el valor si no existe
     * @param int $ttl Tiempo de vida específico
     * @return mixed Valor almacenado o generado
     */
    public function remember($key, $callback, $ttl = 0) {
        // Verificar si el valor existe en caché
        if ($this->has($key)) {
            return $this->get($key);
        }
        
        // Generar el valor
        $value = call_user_func($callback);
        
        // Almacenar en caché
        $this->set($key, $value, $ttl);
        
        return $value;
    }
}
