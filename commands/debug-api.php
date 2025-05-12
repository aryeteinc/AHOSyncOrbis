<?php
/**
 * Script para depurar la estructura de datos de la API
 */

// Cargar configuración y variables de entorno
require_once __DIR__ . '/../src/EnvLoader.php';
require_once __DIR__ . '/../config.php';

// URL de la API
$apiUrl = "https://ahoinmobiliaria.webdgi.site/api/inmueble/restful/list/0c353a42-0bf1-432e-a7f8-6f87bab5f5fe/";

echo "Obteniendo datos de la API: {$apiUrl}\n";

// Realizar la petición a la API
$response = file_get_contents($apiUrl);
if ($response === false) {
    die("Error al obtener datos de la API\n");
}

// Decodificar la respuesta JSON
$data = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die("Error al decodificar respuesta JSON: " . json_last_error_msg() . "\n");
}

// Verificar si hay datos
if (!isset($data['data']) || !is_array($data['data'])) {
    die("No se encontraron datos en la respuesta de la API\n");
}

// Obtener la primera propiedad para analizar su estructura
$property = $data['data'][0];
echo "Analizando estructura de la primera propiedad (Ref: {$property['ref']})\n\n";

// Verificar si hay características y su estructura
echo "Estructura de características:\n";
if (isset($property['caracteristicas'])) {
    echo "- Clave 'caracteristicas' encontrada\n";
    if (is_array($property['caracteristicas'])) {
        echo "  Es un array con " . count($property['caracteristicas']) . " elementos\n";
        if (count($property['caracteristicas']) > 0) {
            echo "  Primer elemento: " . print_r($property['caracteristicas'][0], true) . "\n";
        }
    } else {
        echo "  No es un array, es: " . gettype($property['caracteristicas']) . "\n";
        echo "  Valor: " . print_r($property['caracteristicas'], true) . "\n";
    }
} else {
    echo "- Clave 'caracteristicas' NO encontrada\n";
}

// Verificar si hay imágenes y su estructura
echo "\nEstructura de imágenes:\n";
if (isset($property['imagenes'])) {
    echo "- Clave 'imagenes' encontrada\n";
    if (is_array($property['imagenes'])) {
        echo "  Es un array con " . count($property['imagenes']) . " elementos\n";
        echo "  Primer elemento: " . print_r($property['imagenes'][0], true) . "\n";
    } else {
        echo "  No es un array, es: " . gettype($property['imagenes']) . "\n";
        echo "  Valor: " . print_r($property['imagenes'], true) . "\n";
    }
} else {
    echo "- Clave 'imagenes' NO encontrada\n";
}

if (isset($property['images'])) {
    echo "- Clave 'images' encontrada\n";
    if (is_array($property['images'])) {
        echo "  Es un array con " . count($property['images']) . " elementos\n";
        echo "  Primer elemento: " . print_r($property['images'][0], true) . "\n";
    } else {
        echo "  No es un array, es: " . gettype($property['images']) . "\n";
        echo "  Valor: " . print_r($property['images'], true) . "\n";
    }
} else {
    echo "- Clave 'images' NO encontrada\n";
}

if (isset($property['imagen'])) {
    echo "- Clave 'imagen' encontrada\n";
    echo "  Valor: " . print_r($property['imagen'], true) . "\n";
} else {
    echo "- Clave 'imagen' NO encontrada\n";
}

// Mostrar todas las claves de la propiedad
echo "\nTodas las claves disponibles en la propiedad:\n";
foreach (array_keys($property) as $key) {
    echo "- {$key}\n";
}

// Mostrar la estructura completa de la propiedad
echo "\nEstructura completa de la propiedad:\n";
print_r($property);
