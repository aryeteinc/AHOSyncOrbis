-- Script para corregir las columnas restantes en la tabla properties

-- Modificar columnas en la tabla properties que aún tienen nombres en español
ALTER TABLE `properties` 
  CHANGE `uso_inmueble_id` `property_use_id` INT DEFAULT 1,
  CHANGE `estado_inmueble_id` `property_status_id` INT DEFAULT 1;
