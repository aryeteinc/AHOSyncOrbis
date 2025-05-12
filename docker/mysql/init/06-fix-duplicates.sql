-- Script para corregir las columnas duplicadas en la tabla properties

-- Actualizar los valores de las columnas en inglés con los valores de las columnas en español
UPDATE `properties` SET 
  `property_use_id` = `uso_inmueble_id`,
  `property_status_id` = `estado_inmueble_id`;

-- Eliminar las columnas en español que están duplicadas
ALTER TABLE `properties` 
  DROP COLUMN `uso_inmueble_id`,
  DROP COLUMN `estado_inmueble_id`;
