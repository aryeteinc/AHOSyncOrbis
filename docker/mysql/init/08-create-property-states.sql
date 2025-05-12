-- Script para crear la tabla property_states que falta

-- Crear tabla property_states si no existe
CREATE TABLE IF NOT EXISTS `property_states` (
  `id` int NOT NULL AUTO_INCREMENT,
  `property_ref` varchar(50) NOT NULL,
  `sync_code` varchar(100),
  `is_active` tinyint(1) DEFAULT 1,
  `is_featured` tinyint(1) DEFAULT 0,
  `is_hot` tinyint(1) DEFAULT 0,
  `last_sync` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `property_ref` (`property_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
