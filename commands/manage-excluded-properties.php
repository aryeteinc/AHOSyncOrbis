<?php
/**
 * Script para gestionar propiedades excluidas de la sincronización
 */

// Cargar configuración y variables de entorno
require_once __DIR__ . '/../src/EnvLoader.php';
EnvLoader::load(__DIR__ . '/../config/.env');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Database.php';

echo "======================================================================\n";
echo "GESTIÓN DE PROPIEDADES EXCLUIDAS DE LA SINCRONIZACIÓN\n";
echo "======================================================================\n\n";

// Obtener argumentos de la línea de comandos
$options = getopt('', ['add:', 'remove:', 'list', 'reason:', 'type:']);

// Verificar si se ha proporcionado al menos una opción
if (empty($options)) {
    echo "Uso: php commands/manage-excluded-properties.php [opciones]\n\n";
    echo "Opciones disponibles:\n";
    echo "  --add=VALOR     Agregar una propiedad a la lista de exclusión\n";
    echo "  --remove=VALOR  Eliminar una propiedad de la lista de exclusión\n";
    echo "  --list          Listar todas las propiedades excluidas\n";
    echo "  --reason=TEXT   Especificar una razón para la exclusión (usar con --add)\n";
    echo "  --type=TIPO     Especificar el tipo de identificador (id, ref, sync_code)\n";
    echo "                  Por defecto es 'ref' si no se especifica\n";
    exit(1);
}

try {
    // Obtener conexión a la base de datos
    $db = Database::getInstance();
    $connection = $db->getConnection();
    
    // Verificar si la tabla excluded_properties existe
    $tableExists = $connection->query("SHOW TABLES LIKE 'excluded_properties'")->rowCount() > 0;
    
    if (!$tableExists) {
        echo "La tabla 'excluded_properties' no existe. Ejecute primero sync-complete.php para crear las tablas.\n";
        exit(1);
    }
    
    // Procesar la opción --list
    if (isset($options['list'])) {
        $stmt = $connection->query("SELECT id, identifier, identifier_type, reason, created_at FROM excluded_properties ORDER BY identifier_type, identifier");
        $excludedProperties = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($excludedProperties)) {
            echo "No hay propiedades excluidas de la sincronización.\n";
        } else {
            echo "Propiedades excluidas de la sincronización:\n";
            echo str_repeat('-', 90) . "\n";
            echo sprintf("%-5s | %-15s | %-10s | %-40s | %-19s\n", "ID", "Identificador", "Tipo", "Razón", "Fecha de exclusión");
            echo str_repeat('-', 90) . "\n";
            
            foreach ($excludedProperties as $property) {
                echo sprintf("%-5s | %-15s | %-10s | %-40s | %-19s\n", 
                    $property['id'], 
                    $property['identifier'], 
                    $property['identifier_type'],
                    substr($property['reason'] ?? 'No especificada', 0, 40), 
                    $property['created_at']
                );
            }
            
            echo str_repeat('-', 90) . "\n";
            echo "Total: " . count($excludedProperties) . " propiedades excluidas\n";
        }
    }
    
    // Procesar la opción --add
    if (isset($options['add'])) {
        $identifier = trim($options['add']);
        $reason = isset($options['reason']) ? trim($options['reason']) : null;
        $identifierType = isset($options['type']) ? trim(strtolower($options['type'])) : 'ref';
        
        // Validar el tipo de identificador
        if (!in_array($identifierType, ['id', 'ref', 'sync_code'])) {
            echo "Error: Tipo de identificador no válido. Debe ser 'id', 'ref' o 'sync_code'.\n";
            exit(1);
        }
        
        // Verificar si la propiedad ya está excluida
        $stmt = $connection->prepare("SELECT id FROM excluded_properties WHERE identifier = ? AND identifier_type = ?");
        $stmt->execute([$identifier, $identifierType]);
        $exists = $stmt->fetchColumn();
        
        if ($exists) {
            // Si ya existe, actualizar la razón
            $stmt = $connection->prepare("UPDATE excluded_properties SET reason = ?, updated_at = NOW() WHERE identifier = ? AND identifier_type = ?");
            $stmt->execute([$reason, $identifier, $identifierType]);
            echo "La propiedad con $identifierType '$identifier' ya estaba excluida. Se ha actualizado la razón.\n";
        } else {
            // Si no existe, insertar
            $stmt = $connection->prepare("INSERT INTO excluded_properties (identifier, identifier_type, reason) VALUES (?, ?, ?)");
            $stmt->execute([$identifier, $identifierType, $reason]);
            echo "Propiedad con $identifierType '$identifier' agregada a la lista de exclusión.\n";
            
            // Verificar si la propiedad existe en la base de datos
            $propertyId = null;
            
            if ($identifierType === 'id') {
                // Si el identificador es un ID, usarlo directamente
                $stmt = $connection->prepare("SELECT id FROM properties WHERE id = ?");
                $stmt->execute([$identifier]);
                $propertyId = $stmt->fetchColumn();
            } elseif ($identifierType === 'ref') {
                // Si el identificador es una referencia
                $stmt = $connection->prepare("SELECT id FROM properties WHERE ref = ?");
                $stmt->execute([$identifier]);
                $propertyId = $stmt->fetchColumn();
            } elseif ($identifierType === 'sync_code') {
                // Si el identificador es un código de sincronización
                $stmt = $connection->prepare("SELECT id FROM properties WHERE sync_code = ?");
                $stmt->execute([$identifier]);
                $propertyId = $stmt->fetchColumn();
            }
            
            if ($propertyId) {
                // Obtener la referencia para los directorios de imágenes
                $stmt = $connection->prepare("SELECT ref FROM properties WHERE id = ?");
                $stmt->execute([$propertyId]);
                $propertyRef = $stmt->fetchColumn();
                
                echo "La propiedad existe en la base de datos (ID: $propertyId). Se eliminará en la próxima sincronización.\n";
                
                // Preguntar si se desea eliminar ahora
                echo "¿Desea eliminar la propiedad ahora? (s/n): ";
                $handle = fopen("php://stdin", "r");
                $line = trim(fgets($handle));
                
                if (strtolower($line) === 's' || strtolower($line) === 'si' || strtolower($line) === 'y' || strtolower($line) === 'yes') {
                    // Eliminar imágenes físicas
                    echo "Eliminando imágenes físicas...\n";
                    $stmt = $connection->prepare("SELECT local_url FROM images WHERE property_id = ?");
                    $stmt->execute([$propertyId]);
                    $images = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    $deletedImages = 0;
                    foreach ($images as $imagePath) {
                        if (!empty($imagePath) && file_exists($imagePath)) {
                            unlink($imagePath);
                            $deletedImages++;
                        }
                    }
                    
                    // Eliminar directorios vacíos
                    $propertyDir = $imagesFolder . '/property_' . $propertyRef;
                    $propertyDirAlt = $imagesFolder . '/' . $propertyRef;
                    
                    if (is_dir($propertyDir)) {
                        $files = scandir($propertyDir);
                        $isEmpty = count($files) <= 2; // Solo . y ..
                        
                        if ($isEmpty) {
                            rmdir($propertyDir);
                            echo "Directorio vacío eliminado: $propertyDir\n";
                        }
                    }
                    
                    if (is_dir($propertyDirAlt)) {
                        $files = scandir($propertyDirAlt);
                        $isEmpty = count($files) <= 2; // Solo . y ..
                        
                        if ($isEmpty) {
                            rmdir($propertyDirAlt);
                            echo "Directorio vacío eliminado: $propertyDirAlt\n";
                        }
                    }
                    
                    // Eliminar registros de la base de datos
                    echo "Eliminando registros de la base de datos...\n";
                    
                    // Eliminar características
                    $stmt = $connection->prepare("DELETE FROM property_characteristics WHERE property_id = ?");
                    $stmt->execute([$propertyId]);
                    $deletedCharacteristics = $stmt->rowCount();
                    
                    // Eliminar imágenes
                    $stmt = $connection->prepare("DELETE FROM images WHERE property_id = ?");
                    $stmt->execute([$propertyId]);
                    $deletedImageRecords = $stmt->rowCount();
                    
                    // Eliminar propiedad
                    $stmt = $connection->prepare("DELETE FROM properties WHERE id = ?");
                    $stmt->execute([$propertyId]);
                    
                    echo "Propiedad eliminada con éxito:\n";
                    echo "- Imágenes físicas eliminadas: $deletedImages\n";
                    echo "- Registros de imágenes eliminados: $deletedImageRecords\n";
                    echo "- Características eliminadas: $deletedCharacteristics\n";
                }
            }
        }
    }
    
    // Procesar la opción --remove
    if (isset($options['remove'])) {
        $identifier = trim($options['remove']);
        $identifierType = isset($options['type']) ? trim(strtolower($options['type'])) : 'ref';
        
        // Validar el tipo de identificador
        if (!in_array($identifierType, ['id', 'ref', 'sync_code'])) {
            echo "Error: Tipo de identificador no válido. Debe ser 'id', 'ref' o 'sync_code'.\n";
            exit(1);
        }
        
        // Verificar si la propiedad está excluida
        $stmt = $connection->prepare("SELECT id FROM excluded_properties WHERE identifier = ? AND identifier_type = ?");
        $stmt->execute([$identifier, $identifierType]);
        $exists = $stmt->fetchColumn();
        
        if ($exists) {
            // Eliminar de la lista de exclusión
            $stmt = $connection->prepare("DELETE FROM excluded_properties WHERE identifier = ? AND identifier_type = ?");
            $stmt->execute([$identifier, $identifierType]);
            echo "Propiedad con $identifierType '$identifier' eliminada de la lista de exclusión.\n";
            echo "La propiedad se incluirá en la próxima sincronización.\n";
        } else {
            echo "La propiedad con $identifierType '$identifier' no está en la lista de exclusión.\n";
        }
    }
    
    echo "\nOperación completada exitosamente.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
