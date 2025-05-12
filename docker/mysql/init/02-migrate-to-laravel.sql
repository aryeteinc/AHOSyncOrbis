-- Script para migrar las tablas existentes a las convenciones de Laravel
-- Este script renombra las tablas y columnas existentes

-- Renombrar tablas
RENAME TABLE `asesores` TO `advisors`;
RENAME TABLE `ciudades` TO `cities`;
RENAME TABLE `barrios` TO `neighborhoods`;
RENAME TABLE `tipos_inmueble` TO `property_types`;
RENAME TABLE `usos_inmueble` TO `property_uses`;
RENAME TABLE `estados_inmueble` TO `property_statuses`;
RENAME TABLE `tipo_consignacion` TO `consignment_types`;
RENAME TABLE `inmuebles` TO `properties`;
RENAME TABLE `imagenes` TO `images`;
RENAME TABLE `cambios_inmuebles` TO `property_changes`;
RENAME TABLE `inmuebles_estado` TO `property_sync_statuses`;

-- Modificar columnas en la tabla advisors (antes asesores)
ALTER TABLE `advisors` 
  CHANGE `nombre` `name` varchar(255) NOT NULL,
  CHANGE `apellido` `last_name` varchar(255) DEFAULT NULL,
  CHANGE `email` `email` varchar(255) DEFAULT NULL,
  CHANGE `telefono` `phone` varchar(50) DEFAULT NULL,
  CHANGE `imagen` `image` varchar(255) DEFAULT NULL,
  CHANGE `activo` `active` tinyint(1) DEFAULT 1,
  CHANGE `created_at` `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  CHANGE `updated_at` `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Modificar columnas en la tabla cities (antes ciudades)
ALTER TABLE `cities` 
  CHANGE `nombre` `name` varchar(255) NOT NULL;

-- Modificar columnas en la tabla neighborhoods (antes barrios)
ALTER TABLE `neighborhoods` 
  CHANGE `nombre` `name` varchar(255) NOT NULL,
  CHANGE `ciudad_id` `city_id` int DEFAULT 1;

-- Modificar columnas en la tabla property_types (antes tipos_inmueble)
ALTER TABLE `property_types` 
  CHANGE `nombre` `name` varchar(255) NOT NULL;

-- Modificar columnas en la tabla property_uses (antes usos_inmueble)
ALTER TABLE `property_uses` 
  CHANGE `nombre` `name` varchar(255) NOT NULL;

-- Modificar columnas en la tabla property_statuses (antes estados_inmueble)
ALTER TABLE `property_statuses` 
  CHANGE `nombre` `name` varchar(255) NOT NULL;

-- Modificar columnas en la tabla consignment_types (antes tipo_consignacion)
ALTER TABLE `consignment_types` 
  CHANGE `nombre` `name` varchar(255) NOT NULL,
  CHANGE `descripcion` `description` text DEFAULT NULL;

-- Modificar columnas en la tabla properties (antes inmuebles)
ALTER TABLE `properties` 
  CHANGE `codigo_sincronizacion` `sync_code` VARCHAR(100),
  CHANGE `titulo` `title` VARCHAR(255) NOT NULL,
  CHANGE `descripcion` `description` TEXT,
  CHANGE `descripcion_corta` `short_description` VARCHAR(255),
  CHANGE `direccion` `address` VARCHAR(255),
  CHANGE `precio_venta` `sale_price` DECIMAL(15,2) DEFAULT 0,
  CHANGE `precio_arriendo` `rent_price` DECIMAL(15,2) DEFAULT 0,
  CHANGE `administracion` `administration_fee` DECIMAL(15,2) DEFAULT 0,
  CHANGE `precio_total` `total_price` DECIMAL(15,2) DEFAULT 0,
  CHANGE `area_construida` `built_area` FLOAT DEFAULT 0,
  CHANGE `area_privada` `private_area` FLOAT DEFAULT 0,
  CHANGE `area_total` `total_area` FLOAT DEFAULT 0,
  CHANGE `area_terreno` `land_area` FLOAT DEFAULT 0,
  CHANGE `habitaciones` `bedrooms` INT DEFAULT 0,
  CHANGE `banos` `bathrooms` INT DEFAULT 0,
  CHANGE `garajes` `garages` INT DEFAULT 0,
  CHANGE `estrato` `stratum` INT DEFAULT 0,
  CHANGE `antiguedad` `age` INT DEFAULT 0,
  CHANGE `piso` `floor` INT DEFAULT 0,
  CHANGE `ascensor` `has_elevator` TINYINT(1) DEFAULT 0,
  CHANGE `destacado` `is_featured` TINYINT(1) DEFAULT 0,
  CHANGE `activo` `is_active` TINYINT(1) DEFAULT 1,
  CHANGE `en_caliente` `is_hot` TINYINT(1) DEFAULT 0,
  CHANGE `latitud` `latitude` DECIMAL(10,8) DEFAULT 0,
  CHANGE `longitud` `longitude` DECIMAL(11,8) DEFAULT 0,
  CHANGE `tipo_inmueble_id` `property_type_id` INT DEFAULT 1,
  CHANGE `barrio_id` `neighborhood_id` INT DEFAULT 1,
  CHANGE `ciudad_id` `city_id` INT DEFAULT 1,
  CHANGE `asesor_id` `advisor_id` INT DEFAULT 1,
  CHANGE `uso_id` `property_use_id` INT DEFAULT 1,
  CHANGE `estado_actual_id` `property_status_id` INT DEFAULT 1,
  CHANGE `tipo_consignacion_id` `consignment_type_id` INT DEFAULT 1,
  CHANGE `fecha_creacion` `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CHANGE `fecha_actualizacion` `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Modificar columnas en la tabla images (antes imagenes)
ALTER TABLE `images` 
  CHANGE `inmueble_id` `property_id` INT NOT NULL,
  CHANGE `url_local` `local_url` VARCHAR(255),
  CHANGE `orden` `order` INT DEFAULT 0,
  CHANGE `descargada` `is_downloaded` TINYINT(1) DEFAULT 1,
  CHANGE `fecha_creacion` `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CHANGE `fecha_actualizacion` `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Modificar columnas en la tabla property_changes (antes cambios_inmuebles)
ALTER TABLE `property_changes` 
  CHANGE `inmueble_id` `property_id` INT NOT NULL,
  CHANGE `campo` `field` VARCHAR(50) NOT NULL,
  CHANGE `valor_anterior` `old_value` TEXT,
  CHANGE `valor_nuevo` `new_value` TEXT,
  CHANGE `fecha_cambio` `changed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- Modificar columnas en la tabla property_sync_statuses (antes inmuebles_estado)
ALTER TABLE `property_sync_statuses` 
  CHANGE `inmueble_ref` `property_ref` varchar(50) NOT NULL,
  CHANGE `codigo_sincronizacion` `sync_code` varchar(100),
  CHANGE `activo` `is_active` tinyint(1) DEFAULT 1,
  CHANGE `ultima_sincronizacion` `last_sync` timestamp NULL DEFAULT CURRENT_TIMESTAMP;

-- Actualizar las claves foráneas
ALTER TABLE `neighborhoods` DROP FOREIGN KEY `barrios_ibfk_1`;
ALTER TABLE `neighborhoods` ADD CONSTRAINT `neighborhoods_city_id_foreign` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`);

ALTER TABLE `properties` DROP FOREIGN KEY `inmuebles_ibfk_1`;
ALTER TABLE `properties` DROP FOREIGN KEY `inmuebles_ibfk_2`;
ALTER TABLE `properties` DROP FOREIGN KEY `inmuebles_ibfk_3`;
ALTER TABLE `properties` DROP FOREIGN KEY `inmuebles_ibfk_4`;
ALTER TABLE `properties` DROP FOREIGN KEY `inmuebles_ibfk_5`;
ALTER TABLE `properties` DROP FOREIGN KEY `inmuebles_ibfk_6`;
ALTER TABLE `properties` DROP FOREIGN KEY `inmuebles_ibfk_7`;

ALTER TABLE `properties` 
  ADD CONSTRAINT `properties_property_type_id_foreign` FOREIGN KEY (`property_type_id`) REFERENCES `property_types` (`id`),
  ADD CONSTRAINT `properties_neighborhood_id_foreign` FOREIGN KEY (`neighborhood_id`) REFERENCES `neighborhoods` (`id`),
  ADD CONSTRAINT `properties_city_id_foreign` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`),
  ADD CONSTRAINT `properties_advisor_id_foreign` FOREIGN KEY (`advisor_id`) REFERENCES `advisors` (`id`),
  ADD CONSTRAINT `properties_property_use_id_foreign` FOREIGN KEY (`property_use_id`) REFERENCES `property_uses` (`id`),
  ADD CONSTRAINT `properties_property_status_id_foreign` FOREIGN KEY (`property_status_id`) REFERENCES `property_statuses` (`id`),
  ADD CONSTRAINT `properties_consignment_type_id_foreign` FOREIGN KEY (`consignment_type_id`) REFERENCES `consignment_types` (`id`);

ALTER TABLE `images` DROP FOREIGN KEY `imagenes_ibfk_1`;
ALTER TABLE `images` ADD CONSTRAINT `images_property_id_foreign` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE;

ALTER TABLE `property_changes` DROP FOREIGN KEY `cambios_inmuebles_ibfk_1`;
ALTER TABLE `property_changes` ADD CONSTRAINT `property_changes_property_id_foreign` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE;
