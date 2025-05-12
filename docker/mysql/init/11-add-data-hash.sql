-- Script para agregar la columna data_hash a la tabla properties

-- Verificar si existe la columna hash_datos y renombrarla
SET @exist_hash_datos := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'OrbisAHOPHP' AND TABLE_NAME = 'properties' AND COLUMN_NAME = 'hash_datos');

-- Renombrar columna si existe
SET @query_rename = IF(@exist_hash_datos > 0, 
    'ALTER TABLE properties CHANGE COLUMN hash_datos data_hash VARCHAR(32) NULL;', 
    'SELECT "Columna hash_datos no existe" AS message');
PREPARE stmt FROM @query_rename;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar si existe la columna data_hash
SET @exist_data_hash := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'OrbisAHOPHP' AND TABLE_NAME = 'properties' AND COLUMN_NAME = 'data_hash');

-- Crear columna si no existe
SET @query_add = IF(@exist_data_hash = 0, 
    'ALTER TABLE properties ADD COLUMN data_hash VARCHAR(32) NULL;', 
    'SELECT "Columna data_hash ya existe" AS message');
PREPARE stmt FROM @query_add;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
