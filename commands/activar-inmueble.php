<?php
/**
 * Script para activar manualmente un inmueble
 * 
 * Este script marca un inmueble como activo (activo=1) y elimina su registro
 * de la tabla inmuebles_estado para que futuras sincronizaciones lo mantengan activo.
 * 
 * Uso: php commands/activar-inmueble.php <ref_inmueble>
 * 
 * Ejemplo: php commands/activar-inmueble.php 244
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Logger.php';

// Inicializar logger
$logger = new Logger('activar-inmueble');

// Obtener la referencia del inmueble de los argumentos de línea de comandos
$refInmueble = $argv[1] ?? null;

if (!$refInmueble) {
    $logger->error('Error: Debe especificar la referencia del inmueble.');
    $logger->error('Uso: php commands/activar-inmueble.php <ref_inmueble>');
    exit(1);
}

// Inicializar base de datos
try {
    $db = Database::getInstance();
    $connection = $db->getConnection();
    $logger->info('Conexión a la base de datos establecida');
} catch (Exception $e) {
    $logger->error('Error al conectar a la base de datos: ' . $e->getMessage());
    exit(1);
}

// Función principal para activar un inmueble
function activarInmueble($refInmueble, $db, $logger) {
    try {
        $logger->info("Buscando inmueble con referencia #{$refInmueble}...");
        
        // Verificar si el inmueble existe
        $stmt = $connection->prepare("SELECT * FROM inmuebles WHERE ref = ?");
        $stmt->execute([$refInmueble]);
        $inmueble = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$inmueble) {
            $logger->error("Error: No se encontró ningún inmueble con la referencia #{$refInmueble}");
            return false;
        }
        
        $logger->info("Inmueble #{$refInmueble} encontrado. Estado actual: " . ($inmueble['activo'] ? 'Activo' : 'Inactivo'));
        
        if ($inmueble['activo']) {
            $logger->info("El inmueble #{$refInmueble} ya está activo. No es necesario hacer cambios.");
            return true;
        }
        
        // Marcar el inmueble como activo
        $stmtUpdate = $connection->prepare("UPDATE inmuebles SET activo = 1, updated_at = NOW() WHERE id = ?");
        $stmtUpdate->execute([$inmueble['id']]);
        
        // Eliminar el registro de inmuebles_estado si existe
        $stmtDelete = $connection->prepare("DELETE FROM inmuebles_estado WHERE inmueble_ref = ?");
        $stmtDelete->execute([$refInmueble]);
        $deleted = $stmtDelete->rowCount();
        
        $logger->info("Inmueble #{$refInmueble} marcado como activo.");
        
        if ($deleted > 0) {
            $logger->info("Se eliminó el registro de inmuebles_estado para que futuras sincronizaciones mantengan el inmueble activo.");
        } else {
            $logger->info("No se encontró registro en inmuebles_estado para este inmueble.");
        }
        
        // Registrar el cambio en el historial
        $stmtHistorial = $connection->prepare("
            INSERT INTO cambios_inmuebles (inmueble_id, campo, valor_anterior, valor_nuevo, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmtHistorial->execute([
            $inmueble['id'],
            'activo',
            '0',
            '1',
        ]);
        
        $logger->info("Cambio registrado en el historial.");
        $logger->info("\n¡Operación completada con éxito!");
        
        return true;
    } catch (Exception $e) {
        $logger->error("Error al activar el inmueble: " . $e->getMessage());
        return false;
    }
}

// Ejecutar la función principal
$result = activarInmueble($refInmueble, $connection, $logger);
exit($result ? 0 : 1);
