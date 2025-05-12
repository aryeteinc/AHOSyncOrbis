-- Script de inicialización para la base de datos OrbisAHOPHP
-- Este script crea las tablas necesarias para SyncOrbisPhp siguiendo convenciones de Laravel

-- Asegurarse de que estamos usando la base de datos correcta
USE OrbisAHOPHP;

-- Crear tablas de catálogos
CREATE TABLE IF NOT EXISTS `advisors` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar asesor por defecto
INSERT INTO `advisors` (`id`, `name`, `active`) VALUES (1, 'Oficina', 1);

-- Crear tabla ciudades
CREATE TABLE IF NOT EXISTS `cities` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar ciudad por defecto
INSERT INTO `cities` (`id`, `name`) VALUES (1, 'Bogotá');

-- Crear tabla barrios
CREATE TABLE IF NOT EXISTS `neighborhoods` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `city_id` int DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  FOREIGN KEY (`city_id`) REFERENCES `cities`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar barrio por defecto
INSERT INTO `neighborhoods` (`id`, `name`) VALUES (1, 'Centro');

-- Crear tabla tipos_inmueble
CREATE TABLE IF NOT EXISTS `property_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar tipos de inmueble por defecto
INSERT INTO `property_types` (`id`, `name`) VALUES 
(1, 'Apartamento'),
(2, 'Casa'),
(3, 'Local'),
(4, 'Oficina'),
(5, 'Bodega'),
(6, 'Lote');

-- Crear tabla usos_inmueble
CREATE TABLE IF NOT EXISTS `property_uses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar usos de inmueble por defecto
INSERT INTO `property_uses` (`id`, `name`) VALUES 
(1, 'Vivienda'),
(2, 'Comercial'),
(3, 'Mixto'),
(4, 'Industrial');

-- Crear tabla estados_inmueble
CREATE TABLE IF NOT EXISTS `property_statuses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar estados de inmueble por defecto
INSERT INTO `property_statuses` (`id`, `name`) VALUES 
(1, 'Disponible'),
(2, 'Arrendado'),
(3, 'Vendido');

-- Crear tabla tipo_consignacion
CREATE TABLE IF NOT EXISTS `consignment_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar tipos de consignación por defecto
INSERT INTO `consignment_types` (`id`, `name`) VALUES 
(1, 'Venta'),
(2, 'Arriendo'),
(3, 'Venta y Arriendo');

-- Crear tabla de inmuebles
CREATE TABLE IF NOT EXISTS `properties` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ref` VARCHAR(50) NOT NULL UNIQUE,
  `sync_code` VARCHAR(100),
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `short_description` VARCHAR(255),
  `address` VARCHAR(255),
  `sale_price` DECIMAL(15,2) DEFAULT 0,
  `rent_price` DECIMAL(15,2) DEFAULT 0,
  `administration_fee` DECIMAL(15,2) DEFAULT 0,
  `total_price` DECIMAL(15,2) DEFAULT 0,
  `built_area` FLOAT DEFAULT 0,
  `private_area` FLOAT DEFAULT 0,
  `total_area` FLOAT DEFAULT 0,
  `land_area` FLOAT DEFAULT 0,
  `bedrooms` INT DEFAULT 0,
  `bathrooms` INT DEFAULT 0,
  `garages` INT DEFAULT 0,
  `stratum` INT DEFAULT 0,
  `age` INT DEFAULT 0,
  `floor` INT DEFAULT 0,
  `has_elevator` TINYINT(1) DEFAULT 0,
  `is_featured` TINYINT(1) DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `is_hot` TINYINT(1) DEFAULT 0,
  `latitude` DECIMAL(10,8) DEFAULT 0,
  `longitude` DECIMAL(11,8) DEFAULT 0,
  `slug` VARCHAR(255),
  `property_type_id` INT DEFAULT 1,
  `neighborhood_id` INT DEFAULT 1,
  `city_id` INT DEFAULT 1,
  `advisor_id` INT DEFAULT 1,
  `property_use_id` INT DEFAULT 1,
  `property_status_id` INT DEFAULT 1,
  `consignment_type_id` INT DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`property_type_id`) REFERENCES `property_types`(`id`),
  FOREIGN KEY (`neighborhood_id`) REFERENCES `neighborhoods`(`id`),
  FOREIGN KEY (`city_id`) REFERENCES `cities`(`id`),
  FOREIGN KEY (`advisor_id`) REFERENCES `advisors`(`id`),
  FOREIGN KEY (`property_use_id`) REFERENCES `property_uses`(`id`),
  FOREIGN KEY (`property_status_id`) REFERENCES `property_statuses`(`id`),
  FOREIGN KEY (`consignment_type_id`) REFERENCES `consignment_types`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Crear tabla de imágenes con soporte para Laravel
CREATE TABLE IF NOT EXISTS `images` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `property_id` INT NOT NULL,
  `url` VARCHAR(255) NOT NULL,
  `local_url` VARCHAR(255),
  `disk` VARCHAR(50) NULL,
  `path` VARCHAR(255) NULL,
  `order` INT DEFAULT 0,
  `hash` VARCHAR(32),
  `is_downloaded` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`property_id`),
  FOREIGN KEY (`property_id`) REFERENCES `properties`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Crear tabla para registrar cambios en inmuebles
CREATE TABLE IF NOT EXISTS `property_changes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `property_id` INT NOT NULL,
  `field` VARCHAR(50) NOT NULL,
  `old_value` TEXT,
  `new_value` TEXT,
  `changed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`property_id`),
  FOREIGN KEY (`property_id`) REFERENCES `properties`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Crear tabla inmuebles_estado
CREATE TABLE IF NOT EXISTS `property_sync_statuses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `property_ref` varchar(50) NOT NULL,
  `sync_code` varchar(100),
  `is_active` tinyint(1) DEFAULT 1,
  `last_sync` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `property_ref` (`property_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Crear tabla de sincronización
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

-- Crear tabla de configuración
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

-- Insertar configuraciones por defecto
INSERT INTO `configurations` (`key`, `value`, `description`) VALUES 
('last_sync', NULL, 'Fecha y hora de la última sincronización'),
('sync_interval', '3600', 'Intervalo de sincronización en segundos (por defecto 1 hora)'),
('api_url', 'https://api.orbisaho.com/api/v1/inmuebles', 'URL de la API de Orbis AHO'),
('api_key', '', 'Clave de la API de Orbis AHO'),
('storage_path', '/var/www/html/storage/app/public/inmuebles', 'Ruta de almacenamiento de imágenes'),
('public_url', '/storage/inmuebles', 'URL pública para las imágenes');
