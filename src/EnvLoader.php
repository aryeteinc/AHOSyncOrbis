<?php
/**
 * Clase para cargar variables de entorno desde un archivo .env
 */
class EnvLoader {
    /**
     * Cargar variables de entorno desde un archivo .env
     * 
     * @param string $path Ruta al archivo .env
     * @return bool True si se cargó correctamente, false en caso contrario
     */
    public static function load($path) {
        if (!file_exists($path)) {
            return false;
        }
        
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Ignorar comentarios
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            // Parsear línea como variable=valor
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            
            // Eliminar comillas si existen
            if (strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) {
                $value = substr($value, 1, -1);
            } elseif (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1) {
                $value = substr($value, 1, -1);
            }
            
            // Establecer variable de entorno
            if (!empty($name)) {
                putenv("{$name}={$value}");
                $_ENV[$name] = $value;
            }
        }
        
        return true;
    }
}
