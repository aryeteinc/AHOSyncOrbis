-- Script para eliminar columnas de compatibilidad que ya no son necesarias

-- Verificar si existen las columnas de compatibilidad
SET @exist_uso_id := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'OrbisAHOPHP' AND TABLE_NAME = 'properties' AND COLUMN_NAME = 'uso_id');

SET @exist_estado_actual_id := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'OrbisAHOPHP' AND TABLE_NAME = 'properties' AND COLUMN_NAME = 'estado_actual_id');

-- Agregar columnas de compatibilidad si no existen
SET @query_add_uso_id = IF(@exist_uso_id = 0, 
    'ALTER TABLE properties ADD COLUMN uso_id INT NULL DEFAULT 1;', 
    'SELECT "Columna uso_id ya existe" AS message');
PREPARE stmt FROM @query_add_uso_id;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @query_add_estado_actual_id = IF(@exist_estado_actual_id = 0, 
    'ALTER TABLE properties ADD COLUMN estado_actual_id INT NULL DEFAULT 1;', 
    'SELECT "Columna estado_actual_id ya existe" AS message');
PREPARE stmt FROM @query_add_estado_actual_id;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
