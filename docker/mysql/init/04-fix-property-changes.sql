-- Script para corregir específicamente la tabla property_changes

-- Modificar columnas en la tabla property_changes
ALTER TABLE `property_changes` 
  CHANGE `inmueble_id` `property_id` INT NOT NULL,
  CHANGE `campo` `field` VARCHAR(50) NOT NULL,
  CHANGE `valor_anterior` `old_value` TEXT,
  CHANGE `valor_nuevo` `new_value` TEXT,
  ADD COLUMN `changed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER `new_value`;
