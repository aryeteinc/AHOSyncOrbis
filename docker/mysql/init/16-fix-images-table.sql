-- Script para corregir la estructura de la tabla images según convenciones de Laravel

-- Verificar si existe la tabla images
SET @exist_images := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = 'OrbisAHOPHP' AND TABLE_NAME = 'images');

-- Si la tabla no existe, crearla
SET @query_create_images = IF(@exist_images = 0, 
    'CREATE TABLE images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        property_id INT NOT NULL,
        url VARCHAR(255) NOT NULL,
        local_url VARCHAR(255) NULL,
        order_num INT DEFAULT 0,
        is_downloaded TINYINT(1) DEFAULT 0,
        laravel_disk VARCHAR(50) NULL,
        laravel_path VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (property_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;', 
    'SELECT "Tabla images ya existe" AS message');
PREPARE stmt FROM @query_create_images;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar si existe la columna local_url
SET @exist_local_url := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'OrbisAHOPHP' AND TABLE_NAME = 'images' AND COLUMN_NAME = 'local_url');

-- Si la columna no existe, agregarla
SET @query_add_local_url = IF(@exist_local_url = 0, 
    'ALTER TABLE images ADD COLUMN local_url VARCHAR(255) NULL AFTER url;', 
    'SELECT "Columna local_url ya existe" AS message');
PREPARE stmt FROM @query_add_local_url;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar si existe la columna order_num
SET @exist_order_num := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'OrbisAHOPHP' AND TABLE_NAME = 'images' AND COLUMN_NAME = 'order_num');

-- Si la columna no existe, agregarla
SET @query_add_order_num = IF(@exist_order_num = 0, 
    'ALTER TABLE images ADD COLUMN order_num INT DEFAULT 0 AFTER local_url;', 
    'SELECT "Columna order_num ya existe" AS message');
PREPARE stmt FROM @query_add_order_num;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar si existe la columna is_downloaded
SET @exist_is_downloaded := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'OrbisAHOPHP' AND TABLE_NAME = 'images' AND COLUMN_NAME = 'is_downloaded');

-- Si la columna no existe, agregarla
SET @query_add_is_downloaded = IF(@exist_is_downloaded = 0, 
    'ALTER TABLE images ADD COLUMN is_downloaded TINYINT(1) DEFAULT 0 AFTER order_num;', 
    'SELECT "Columna is_downloaded ya existe" AS message');
PREPARE stmt FROM @query_add_is_downloaded;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar si existe la columna laravel_disk
SET @exist_laravel_disk := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'OrbisAHOPHP' AND TABLE_NAME = 'images' AND COLUMN_NAME = 'laravel_disk');

-- Si la columna no existe, agregarla
SET @query_add_laravel_disk = IF(@exist_laravel_disk = 0, 
    'ALTER TABLE images ADD COLUMN laravel_disk VARCHAR(50) NULL AFTER is_downloaded;', 
    'SELECT "Columna laravel_disk ya existe" AS message');
PREPARE stmt FROM @query_add_laravel_disk;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar si existe la columna laravel_path
SET @exist_laravel_path := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'OrbisAHOPHP' AND TABLE_NAME = 'images' AND COLUMN_NAME = 'laravel_path');

-- Si la columna no existe, agregarla
SET @query_add_laravel_path = IF(@exist_laravel_path = 0, 
    'ALTER TABLE images ADD COLUMN laravel_path VARCHAR(255) NULL AFTER laravel_disk;', 
    'SELECT "Columna laravel_path ya existe" AS message');
PREPARE stmt FROM @query_add_laravel_path;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
