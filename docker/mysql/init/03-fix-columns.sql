-- Script para corregir las columnas de las tablas que ya tienen nombres en inglés
-- pero que aún tienen columnas en español

-- Modificar columnas en la tabla property_changes
ALTER TABLE `property_changes` 
  CHANGE `inmueble_id` `property_id` INT NOT NULL,
  CHANGE `campo` `field` VARCHAR(50) NOT NULL,
  CHANGE `valor_anterior` `old_value` TEXT,
  CHANGE `valor_nuevo` `new_value` TEXT,
  ADD COLUMN `changed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER `new_value`;

-- Verificar y modificar columnas en la tabla caracteristica_inmueble si existe
ALTER TABLE `caracteristica_inmueble` RENAME TO `characteristic_property`;

-- Verificar y modificar columnas en la tabla caracteristicas si existe
ALTER TABLE `caracteristicas` RENAME TO `characteristics`;

-- Modificar columnas en la tabla characteristics
ALTER TABLE `characteristics` 
  CHANGE `nombre` `name` VARCHAR(255) NOT NULL;

-- Modificar columnas en la tabla characteristic_property
ALTER TABLE `characteristic_property` 
  CHANGE `inmueble_id` `property_id` INT NOT NULL,
  CHANGE `caracteristica_id` `characteristic_id` INT NOT NULL;

-- Verificar y modificar columnas en otras tablas que puedan tener mezcla de idiomas
ALTER TABLE `inmueble_caracteristicas` RENAME TO `property_characteristics`;

-- Modificar columnas en la tabla property_characteristics si existe
ALTER TABLE `property_characteristics` 
  CHANGE `inmueble_id` `property_id` INT NOT NULL,
  CHANGE `caracteristica_id` `characteristic_id` INT NOT NULL;

-- Verificar y modificar columnas en la tabla caracteristicas_inmueble si existe
ALTER TABLE `caracteristicas_inmueble` RENAME TO `property_characteristic_values`;

-- Modificar columnas en la tabla property_characteristic_values si existe
ALTER TABLE `property_characteristic_values` 
  CHANGE `inmueble_id` `property_id` INT NOT NULL,
  CHANGE `caracteristica_id` `characteristic_id` INT NOT NULL,
  CHANGE `valor` `value` TEXT;
