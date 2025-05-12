-- Script para corregir nombres de columnas en la tabla properties según convenciones de Laravel

-- Verificar si existen las columnas con nombres en español y renombrarlas
SET @exist_tipo_property_id := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'OrbisAHOPHP' AND TABLE_NAME = 'properties' AND COLUMN_NAME = 'tipo_property_id');

SET @exist_uso_property_id := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'OrbisAHOPHP' AND TABLE_NAME = 'properties' AND COLUMN_NAME = 'uso_property_id');

SET @exist_estado_property_id := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'OrbisAHOPHP' AND TABLE_NAME = 'properties' AND COLUMN_NAME = 'estado_property_id');

SET @exist_description_corta := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'OrbisAHOPHP' AND TABLE_NAME = 'properties' AND COLUMN_NAME = 'description_corta');

-- Renombrar columnas si existen
SET @query_tipo = IF(@exist_tipo_property_id > 0, 
    'ALTER TABLE properties CHANGE COLUMN tipo_property_id property_type_id INT NULL DEFAULT 1;', 
    'SELECT "Columna property_type_id ya existe" AS message');
PREPARE stmt FROM @query_tipo;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @query_uso = IF(@exist_uso_property_id > 0, 
    'ALTER TABLE properties CHANGE COLUMN uso_property_id property_use_id INT NULL DEFAULT 1;', 
    'SELECT "Columna property_use_id ya existe" AS message');
PREPARE stmt FROM @query_uso;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @query_estado = IF(@exist_estado_property_id > 0, 
    'ALTER TABLE properties CHANGE COLUMN estado_property_id property_status_id INT NULL DEFAULT 1;', 
    'SELECT "Columna property_status_id ya existe" AS message');
PREPARE stmt FROM @query_estado;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @query_desc = IF(@exist_description_corta > 0, 
    'ALTER TABLE properties CHANGE COLUMN description_corta short_description TEXT NULL;', 
    'SELECT "Columna short_description ya existe" AS message');
PREPARE stmt FROM @query_desc;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar si faltan columnas y crearlas
SET @exist_property_type_id := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'OrbisAHOPHP' AND TABLE_NAME = 'properties' AND COLUMN_NAME = 'property_type_id');

SET @exist_property_use_id := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'OrbisAHOPHP' AND TABLE_NAME = 'properties' AND COLUMN_NAME = 'property_use_id');

SET @exist_property_status_id := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'OrbisAHOPHP' AND TABLE_NAME = 'properties' AND COLUMN_NAME = 'property_status_id');

SET @exist_short_description := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'OrbisAHOPHP' AND TABLE_NAME = 'properties' AND COLUMN_NAME = 'short_description');

-- Crear columnas si no existen
SET @query_add_type = IF(@exist_property_type_id = 0, 
    'ALTER TABLE properties ADD COLUMN property_type_id INT NULL DEFAULT 1;', 
    'SELECT "Columna property_type_id ya existe" AS message');
PREPARE stmt FROM @query_add_type;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @query_add_use = IF(@exist_property_use_id = 0, 
    'ALTER TABLE properties ADD COLUMN property_use_id INT NULL DEFAULT 1;', 
    'SELECT "Columna property_use_id ya existe" AS message');
PREPARE stmt FROM @query_add_use;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @query_add_status = IF(@exist_property_status_id = 0, 
    'ALTER TABLE properties ADD COLUMN property_status_id INT NULL DEFAULT 1;', 
    'SELECT "Columna property_status_id ya existe" AS message');
PREPARE stmt FROM @query_add_status;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @query_add_short_desc = IF(@exist_short_description = 0, 
    'ALTER TABLE properties ADD COLUMN short_description TEXT NULL;', 
    'SELECT "Columna short_description ya existe" AS message');
PREPARE stmt FROM @query_add_short_desc;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
