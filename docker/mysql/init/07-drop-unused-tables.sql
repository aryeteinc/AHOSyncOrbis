-- Script para eliminar tablas que no se están usando
-- Este script elimina tablas que no siguen las convenciones de Laravel o que están duplicadas

-- Verificar y eliminar tablas relacionadas con características que podrían estar duplicadas
-- Primero verificamos si existen y luego las eliminamos si es necesario

-- Tabla 'ejecuciones' (debería ser 'executions' según convenciones de Laravel)
DROP TABLE IF EXISTS `ejecuciones`;

-- Verificar si hay tablas con nombres en español que ya no se usan
-- porque han sido reemplazadas por sus equivalentes en inglés
DROP TABLE IF EXISTS `asesores`;
DROP TABLE IF EXISTS `ciudades`;
DROP TABLE IF EXISTS `barrios`;
DROP TABLE IF EXISTS `tipos_inmueble`;
DROP TABLE IF EXISTS `usos_inmueble`;
DROP TABLE IF EXISTS `estados_inmueble`;
DROP TABLE IF EXISTS `tipo_consignacion`;
DROP TABLE IF EXISTS `inmuebles`;
DROP TABLE IF EXISTS `imagenes`;
DROP TABLE IF EXISTS `cambios_inmuebles`;
DROP TABLE IF EXISTS `inmuebles_estado`;

-- Eliminar tablas relacionadas con características que podrían estar duplicadas
-- Verificamos cuáles son las tablas correctas según las convenciones de Laravel
-- y eliminamos las que no se usan

-- Verificar si hay tablas duplicadas para características
DROP TABLE IF EXISTS `caracteristicas`;
DROP TABLE IF EXISTS `caracteristica_inmueble`;
DROP TABLE IF EXISTS `caracteristicas_inmueble`;
DROP TABLE IF EXISTS `inmueble_caracteristicas`;

-- Verificar si hay otras tablas que no siguen las convenciones de Laravel
DROP TABLE IF EXISTS `configuraciones`;
DROP TABLE IF EXISTS `sincronizacion_logs`;

-- Crear tabla de configuración si no existe (siguiendo convenciones de Laravel)
CREATE TABLE IF NOT EXISTS `configurations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `key` varchar(100) NOT NULL,
  `value` text,
  `description` varchar(255),
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar configuraciones por defecto si la tabla está vacía
INSERT INTO `configurations` (`key`, `value`, `description`)
SELECT 'last_sync', NULL, 'Fecha y hora de la última sincronización'
FROM dual
WHERE NOT EXISTS (SELECT 1 FROM `configurations` WHERE `key` = 'last_sync');

INSERT INTO `configurations` (`key`, `value`, `description`)
SELECT 'sync_interval', '3600', 'Intervalo de sincronización en segundos (por defecto 1 hora)'
FROM dual
WHERE NOT EXISTS (SELECT 1 FROM `configurations` WHERE `key` = 'sync_interval');

INSERT INTO `configurations` (`key`, `value`, `description`)
SELECT 'api_url', 'https://api.orbisaho.com/api/v1/inmuebles', 'URL de la API de Orbis AHO'
FROM dual
WHERE NOT EXISTS (SELECT 1 FROM `configurations` WHERE `key` = 'api_url');

INSERT INTO `configurations` (`key`, `value`, `description`)
SELECT 'api_key', '', 'Clave de la API de Orbis AHO'
FROM dual
WHERE NOT EXISTS (SELECT 1 FROM `configurations` WHERE `key` = 'api_key');

INSERT INTO `configurations` (`key`, `value`, `description`)
SELECT 'storage_path', '/var/www/html/storage/app/public/inmuebles', 'Ruta de almacenamiento de imágenes'
FROM dual
WHERE NOT EXISTS (SELECT 1 FROM `configurations` WHERE `key` = 'storage_path');

INSERT INTO `configurations` (`key`, `value`, `description`)
SELECT 'public_url', '/storage/inmuebles', 'URL pública para las imágenes'
FROM dual
WHERE NOT EXISTS (SELECT 1 FROM `configurations` WHERE `key` = 'public_url');

-- Crear tabla de logs de sincronización si no existe
CREATE TABLE IF NOT EXISTS `sync_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `start_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `end_time` timestamp NULL DEFAULT NULL,
  `properties_processed` int DEFAULT 0,
  `properties_created` int DEFAULT 0,
  `properties_updated` int DEFAULT 0,
  `properties_deactivated` int DEFAULT 0,
  `images_downloaded` int DEFAULT 0,
  `status` varchar(50) DEFAULT 'running',
  `error_message` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
