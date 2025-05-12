<?php
/**
 * Script de depuración para la sincronización
 * Este script muestra la estructura de la respuesta de la API
 */

// Cargar clases necesarias
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/EnvLoader.php';

// Cargar variables de entorno
$envPath = __DIR__ . '/../config/.env';
if (file_exists($envPath)) {
    EnvLoader::load($envPath);
}

// Obtener la URL de la API
$apiUrl = getenv('API_URL') ?: 'https://api.example.com/';
echo "Obteniendo datos de la API: {$apiUrl}\n";

// Configurar opciones para la solicitud HTTP
$context = stream_context_create([
    'http' => [
        'timeout' => 30, // Timeout de 30 segundos
        'user_agent' => 'SyncOrbisPhp/1.0'
    ]
]);

// Realizar la solicitud
echo "Realizando solicitud a la API...\n";
$response = file_get_contents($apiUrl, false, $context);

if ($response === false) {
    echo "Error: No se pudo obtener respuesta de la API\n";
    exit(1);
}

echo "Respuesta recibida. Tamaño: " . strlen($response) . " bytes\n";
echo "Primeros 500 caracteres de la respuesta:\n";
echo substr($response, 0, 500) . "\n...\n";

// Decodificar la respuesta JSON
$data = json_decode($response, true);

if ($data === null) {
    echo "Error: No se pudo decodificar la respuesta como JSON\n";
    exit(1);
}

echo "\nEstructura de la respuesta:\n";
print_r($data);

// Analizar la estructura para encontrar los inmuebles
echo "\nBuscando datos de inmuebles en la respuesta...\n";

// Verificar si la respuesta tiene la estructura esperada
if (isset($data['data'])) {
    echo "Encontrado campo 'data'\n";
    
    if (is_array($data['data'])) {
        echo "El campo 'data' es un array\n";
        
        // Verificar si hay un campo 'inmuebles' dentro de 'data'
        if (isset($data['data']['inmuebles'])) {
            echo "Encontrado campo 'data.inmuebles'\n";
            
            if (is_array($data['data']['inmuebles'])) {
                echo "El campo 'data.inmuebles' es un array con " . count($data['data']['inmuebles']) . " elementos\n";
                
                if (!empty($data['data']['inmuebles'])) {
                    echo "Primer elemento de 'data.inmuebles':\n";
                    print_r(reset($data['data']['inmuebles']));
                }
            } else {
                echo "El campo 'data.inmuebles' NO es un array\n";
            }
        } else {
            echo "No se encontró el campo 'inmuebles' dentro de 'data'\n";
            
            // Verificar si 'data' directamente contiene los inmuebles
            $firstElement = reset($data['data']);
            if (is_array($firstElement)) {
                echo "El primer elemento de 'data' es un array\n";
                
                echo "Estructura completa del primer elemento de 'data':\n";
                print_r($firstElement);
                
                echo "\nClaves del primer elemento:\n";
                print_r(array_keys($firstElement));
                
                if (isset($firstElement['referencia']) || isset($firstElement['ref'])) {
                    echo "\nEl primer elemento de 'data' parece ser un inmueble (tiene campo 'referencia' o 'ref')\n";
                } else {
                    echo "\nBuscando campos que puedan identificar un inmueble...\n";
                    foreach ($firstElement as $key => $value) {
                        echo "Campo '{$key}': " . (is_array($value) ? "[Array]" : $value) . "\n";
                    }
                }
                
                echo "Total de elementos en 'data': " . count($data['data']) . "\n";
            } else {
                echo "El primer elemento de 'data' NO es un array\n";
            }
        }
    } else {
        echo "El campo 'data' NO es un array\n";
    }
} else if (isset($data['inmuebles'])) {
    echo "Encontrado campo 'inmuebles' en la raíz\n";
    
    if (is_array($data['inmuebles'])) {
        echo "El campo 'inmuebles' es un array con " . count($data['inmuebles']) . " elementos\n";
        
        if (!empty($data['inmuebles'])) {
            echo "Primer elemento de 'inmuebles':\n";
            print_r(reset($data['inmuebles']));
        }
    } else {
        echo "El campo 'inmuebles' NO es un array\n";
    }
} else {
    echo "No se encontró ningún campo conocido ('data' o 'inmuebles')\n";
    echo "Claves disponibles en la raíz:\n";
    print_r(array_keys($data));
}

echo "\nAnálisis completado.\n";
