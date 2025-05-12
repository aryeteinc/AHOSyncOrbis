<?php
/**
 * Script principal para ejecutar la sincronización
 * 
 * Uso: php sync.php [opciones]
 * 
 * Opciones:
 *   -l, --limit=N       Limitar a N propiedades (predeterminado: sin límite)
 *   -f, --force         Forzar sincronización completa (ignorar hashes)
 *   --no-images         No descargar imágenes
 *   --reset             Restablecer la base de datos antes de sincronizar
 *   --help              Mostrar ayuda
 */

// Cargar el comando de sincronización
require_once __DIR__ . '/commands/sync.php';
