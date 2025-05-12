<?php
/**
 * Script para verificar las imágenes en la respuesta de la API
 */

// Cargar configuración y variables de entorno
require_once __DIR__ . '/../src/EnvLoader.php';
require_once __DIR__ . '/../config.php';

// Obtener datos de la API desde el archivo .env
$apiUrl = getenv('API_URL') ?: 'https://ahoinmobiliaria.webdgi.site/api/inmueble/restful/list/0c353a42-0bf1-432e-a7f8-6f87bab5f5fe/';
echo "Obteniendo datos de la API: {$apiUrl}\n";

$response = file_get_contents($apiUrl);
$data = json_decode($response, true);

// Verificar estructura de la respuesta
if (isset($data['data']) && is_array($data['data'])) {
    $properties = $data['data'];
    echo "Se encontraron " . count($properties) . " propiedades en la respuesta\n";
    
    // Verificar las primeras 5 propiedades
    $limit = min(5, count($properties));
    
    for ($i = 0; $i < $limit; $i++) {
        $property = $properties[$i];
        $ref = $property['ref'] ?? 'Sin referencia';
        
        echo "\n=== Propiedad #{$ref} ===\n";
        
        // Buscar imágenes en diferentes posibles ubicaciones
        $possibleImageKeys = ['imagenes', 'images', 'fotos', 'photos', 'gallery', 'galeria'];
        $imagesFound = false;
        
        foreach ($possibleImageKeys as $key) {
            if (isset($property[$key]) && !empty($property[$key])) {
                $images = $property[$key];
                $count = is_array($images) ? count($images) : 1;
                echo "Se encontraron {$count} imágenes en la clave '{$key}'\n";
                
                // Mostrar detalles de las imágenes
                if (is_array($images)) {
                    foreach ($images as $index => $image) {
                        if (is_array($image)) {
                            echo "  Imagen " . ($index + 1) . ": ";
                            foreach ($image as $imgKey => $imgValue) {
                                if (is_string($imgValue)) {
                                    echo "{$imgKey}='{$imgValue}' ";
                                }
                            }
                            echo "\n";
                        } else {
                            echo "  Imagen " . ($index + 1) . ": {$image}\n";
                        }
                    }
                } else {
                    echo "  Imagen: {$images}\n";
                }
                
                $imagesFound = true;
                break;
            }
        }
        
        if (!$imagesFound) {
            echo "No se encontraron imágenes para esta propiedad\n";
            
            // Mostrar todas las claves disponibles para depuración
            echo "Claves disponibles: " . implode(", ", array_keys($property)) . "\n";
        }
    }
} else {
    echo "Estructura de respuesta no reconocida\n";
    print_r($data);
}
