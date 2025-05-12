-- Script para agregar columnas faltantes a la tabla properties

-- Verificar si existe la columna land_area y crearla si no existe
SET @exist_land_area := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'OrbisAHOPHP' AND TABLE_NAME = 'properties' AND COLUMN_NAME = 'land_area');

SET @query_add_land_area = IF(@exist_land_area = 0, 
    'ALTER TABLE properties ADD COLUMN land_area DECIMAL(10,2) NULL DEFAULT 0;', 
    'SELECT "Columna land_area ya existe" AS message');
PREPARE stmt FROM @query_add_land_area;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar si existe la columna uso_id y crearla si no existe
SET @exist_uso_id := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'OrbisAHOPHP' AND TABLE_NAME = 'properties' AND COLUMN_NAME = 'uso_id');

SET @query_add_uso_id = IF(@exist_uso_id = 0, 
    'ALTER TABLE properties ADD COLUMN uso_id INT NULL DEFAULT 1;', 
    'SELECT "Columna uso_id ya existe" AS message');
PREPARE stmt FROM @query_add_uso_id;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar si existe la columna estado_actual_id y crearla si no existe
SET @exist_estado_actual_id := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'OrbisAHOPHP' AND TABLE_NAME = 'properties' AND COLUMN_NAME = 'estado_actual_id');

SET @query_add_estado_actual_id = IF(@exist_estado_actual_id = 0, 
    'ALTER TABLE properties ADD COLUMN estado_actual_id INT NULL DEFAULT 1;', 
    'SELECT "Columna estado_actual_id ya existe" AS message');
PREPARE stmt FROM @query_add_estado_actual_id;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
