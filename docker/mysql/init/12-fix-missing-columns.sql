-- Script para corregir columnas faltantes en la tabla properties

-- Verificar si existe la columna land_area y crearla si no existe
SET @exist_land_area := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'OrbisAHOPHP' AND TABLE_NAME = 'properties' AND COLUMN_NAME = 'land_area');

SET @query_add_land_area = IF(@exist_land_area = 0, 
    'ALTER TABLE properties ADD COLUMN land_area DECIMAL(10,2) NULL DEFAULT 0;', 
    'SELECT "Columna land_area ya existe" AS message');
PREPARE stmt FROM @query_add_land_area;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Eliminar la columna total_price si existe (no se usa según convenciones Laravel)
SET @exist_total_price := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'OrbisAHOPHP' AND TABLE_NAME = 'properties' AND COLUMN_NAME = 'total_price');

SET @query_drop_total_price = IF(@exist_total_price > 0, 
    'ALTER TABLE properties DROP COLUMN total_price;', 
    'SELECT "Columna total_price no existe" AS message');
PREPARE stmt FROM @query_drop_total_price;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
