<?php
/**
 * Clase para procesar propiedades inmobiliarias
 */
class PropertyProcessor {
    private $db;
    private $imagesFolder;
    private $downloadImages;
    private $trackChanges;
    private $stats;
    
    /**
     * Constructor
     * 
     * @param PDO $db Instancia de la base de datos
     * @param string $imagesFolder Carpeta para las imágenes
     * @param bool $downloadImages Si se deben descargar imágenes
     * @param bool $trackChanges Si se deben registrar cambios
     * @param array &$stats Referencia a las estadísticas de sincronización
     */
    public function __construct(PDO $db, $imagesFolder, $downloadImages = true, $trackChanges = true, &$stats = []) {
        $this->db = $db;
        $this->imagesFolder = $imagesFolder;
        $this->downloadImages = $downloadImages;
        $this->trackChanges = $trackChanges;
        
        // Inicializar estadísticas para evitar warnings
        if (!isset($stats['inmuebles_procesados'])) $stats['inmuebles_procesados'] = 0;
        if (!isset($stats['inmuebles_nuevos'])) $stats['inmuebles_nuevos'] = 0;
        if (!isset($stats['inmuebles_actualizados'])) $stats['inmuebles_actualizados'] = 0;
        if (!isset($stats['inmuebles_sin_cambios'])) $stats['inmuebles_sin_cambios'] = 0;
        if (!isset($stats['imagenes_descargadas'])) $stats['imagenes_descargadas'] = 0;
        if (!isset($stats['imagenes_eliminadas'])) $stats['imagenes_eliminadas'] = 0;
        if (!isset($stats['errores'])) $stats['errores'] = 0;
        
        $this->stats = &$stats;
    }
    
    /**
     * Obtener una propiedad existente por su referencia
     * 
     * @param string $propertyRef Referencia de la propiedad
     * @return array|bool Datos de la propiedad o false si no existe
     */
    private function getExistingProperty($propertyRef) {
        $stmt = $this->db->prepare("SELECT * FROM properties WHERE sync_code = ?");
        $stmt->execute([$propertyRef]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: false;
    }
    
    /**
     * Procesar una propiedad
     * 
     * @param array $property Datos de la propiedad
     * @return int ID de referencia de la propiedad
     */
    public function processProperty($property) {
        try {
            $this->stats['inmuebles_procesados']++;
            
            // Obtener referencia del inmueble
            $propertyRef = $property['ref']; // Usamos ref como identificador interno, pero sync_code para la BD
            
            // Manejar el código de sincronización
            // Verificar si existe el campo codigo_consignacion_sincronizacion
            if (isset($property['codigo_consignacion_sincronizacion'])) {
                if (!empty($property['codigo_consignacion_sincronizacion'])) {
                    $property['sync_code'] = $property['codigo_consignacion_sincronizacion'];
                    echo "Inmueble #{$propertyRef}: Código de sincronización desde API: {$property['sync_code']}\n";
                } else {
                    // Si el código de sincronización es null o vacío, guardarlo como null
                    $property['sync_code'] = null;
                    echo "Inmueble #{$propertyRef}: Código de sincronización es NULL\n";
                }
            } else if (!isset($property['sync_code']) || empty($property['sync_code'])) {
                // Si no hay código de sincronización, guardarlo como null
                $property['sync_code'] = null;
                echo "Inmueble #{$propertyRef}: No tiene código de sincronización, guardando como NULL\n";
            } else {
                echo "Inmueble #{$propertyRef}: Código de sincronización: {$property['sync_code']}\n";
            }
            
            // Verificar si el inmueble ya existe en la base de datos
            $existingProperty = $this->getExistingProperty($propertyRef);
            
            if ($existingProperty) {
                echo "Inmueble #{$propertyRef}: Encontrado en la base de datos con ID {$existingProperty['id']}\n";
            } else {
                echo "Inmueble #{$propertyRef}: No encontrado en la base de datos\n";
            }
            
            // Mapear campos de la API a campos de la base de datos
            // Usar los campos directos de la API si están disponibles
            // Limitar el título a 255 caracteres (tamaño de la columna en la base de datos)
            $property['title'] = substr($property['observacion'] ?? '', 0, 255);
            $property['description'] = $property['observacion_portales'] ?? '';
            $property['sale_price'] = $property['valor_venta'] ?? 0;
            $property['rent_price'] = $property['valor_canon'] ?? 0;
            
            // Normalizar campos
            $property['administration_fee'] = $property['valor_admon'] ?? 0;
            $property['bedrooms'] = $property['alcobas'] ?? 0;
            $property['bathrooms'] = $property['baños'] ?? 0;
            
            // Guardar los names de los campos relacionales para referencia
            $ciudad_name = $property['ciudad'] ?? '';
            $barrio_name = $property['barrio'] ?? '';
            $tipo_inmueble_name = $property['tipo_inmueble'] ?? '';
            $uso_inmueble_name = $property['uso'] ?? '';
            $estado_inmueble_name = $property['estado_actual'] ?? '';
            $tipo_consignacion_name = $property['tipo_consignacion'] ?? '';
            
            // Mostrar datos relacionales para depuración
            echo "Inmueble #{$propertyRef}: Datos relacionales:\n";
            echo "  Ciudad: {$ciudad_name}\n";
            echo "  Barrio: {$barrio_name}\n";
            echo "  Tipo: {$tipo_inmueble_name}\n";
            echo "  Uso: {$uso_inmueble_name}\n";
            echo "  Estado: {$estado_inmueble_name}\n";
            echo "  Tipo Consignación: {$tipo_consignacion_name}\n";
            echo "  Tipo Consignación: {$tipo_consignacion_name}\n";
            
            // Preservar campos relacionales si el inmueble ya existe
            if ($existingProperty) {
                $this->preserveExistingRelations($property, $existingProperty);
            } else {
                $this->setDefaultRelations($property, $tipo_inmueble_name, $uso_inmueble_name, $estado_inmueble_name);
            }
            
            // Validar y limpiar precios para evitar errores de rango en MySQL
            $this->cleanPrices($property);
            
            // Generar hash de datos para detectar cambios
            $nuevoHash = $this->calculateDataHash($property);
            
            // Procesar el inmueble según si existe o no
            if ($existingProperty) {
                // Si el inmueble ya existe, procesar como actualización
                $this->processExistingProperty($property, $existingProperty, $nuevoHash);
                // Si es un inmueble existente, usar el ID del inmueble existente
                $propertyId = $existingProperty['id'];
            } else {
                // Si el inmueble no existe, procesar como nuevo
                $propertyId = $this->processNewProperty($property, $nuevoHash);
            }
            
            // Si aún no tenemos ID, intentar buscarlo en la base de datos
            if (!$propertyId && isset($property['ref'])) {
                // Buscar el inmueble por referencia
                $stmt = $this->db->prepare("SELECT id FROM properties WHERE sync_code = ?");
                $stmt->execute([$property['ref']]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $propertyId = $result ? $result['id'] : null;
            }
            
            // Guardar el ID en el array de la propiedad
            $property['id'] = $propertyId;
            
            // Depurar información sobre características
            echo "Inmueble #{$property['ref']}: Depuración de características:\n";
            echo "  - propertyId: " . ($propertyId ? $propertyId : 'no disponible') . "\n";
            echo "  - isset(property['caracteristicas']): " . (isset($property['caracteristicas']) ? 'true' : 'false') . "\n";
            if (isset($property['caracteristicas'])) {
                echo "  - is_array(property['caracteristicas']): " . (is_array($property['caracteristicas']) ? 'true' : 'false') . "\n";
                if (is_array($property['caracteristicas'])) {
                    echo "  - count(property['caracteristicas']): " . count($property['caracteristicas']) . "\n";
                }
            }
            
            // Procesar características si existen y tenemos ID
            if ($propertyId && isset($property['caracteristicas']) && is_array($property['caracteristicas'])) {
                echo "Inmueble #{$property['ref']}: Procesando " . count($property['caracteristicas']) . " características\n";
                $this->processCharacteristics($propertyId, $property['caracteristicas']);
            } else {
                echo "Inmueble #{$property['ref']}: No se encontraron características para procesar\n";
            }
            
            // Verificar si hay imágenes en diferentes formatos de la API
            // La API puede enviar imágenes en property['imagenes'] o property['images']
            if (!isset($property['imagenes']) && isset($property['images'])) {
                $property['imagenes'] = $property['images'];
            }
            
            // También puede enviar una sola imagen en property['imagen']
            if (!isset($property['imagenes']) && isset($property['imagen'])) {
                $property['imagenes'] = [$property['imagen']];
            }
            
            // Verificar si hay imágenes para procesar
            if ($propertyId && $this->downloadImages) {
                if (isset($property['imagenes']) && is_array($property['imagenes']) && !empty($property['imagenes'])) {
                    // Procesar las imágenes
                    $this->processPropertyImages($property);
                } else {
                    // La API devuelve un array vacío para imágenes, así que simplemente informamos
                    echo "Inmueble #{$property['ref']}: No hay imágenes disponibles para procesar\n";
                }
            }
            
            return $property['ref'];
        } catch (Exception $e) {
            echo "Error procesando inmueble #{$property['ref']}: {$e->getMessage()}\n";
            $this->stats['errores']++;
            throw $e;
        }
    }
    
    /**
     * Preservar relaciones existentes de un inmueble
     * 
     * @param array &$property Datos de la propiedad
     * @param array $existingProperty Datos existentes de la propiedad
     */
    private function preserveExistingRelations(&$property, $existingProperty) {
        // Mantener los campos relacionales que ya existen en la base de datos
        $property['city_id'] = $existingProperty['city_id'];
        
        // Preservar campos que pueden ser modificados manualmente
        // Estos campos no se actualizarán durante la sincronización
        $property['is_active'] = $existingProperty['is_active'];
        $property['advisor_id'] = $existingProperty['advisor_id'];
        
        // Si el campo is_featured ha sido modificado manualmente, preservarlo
        if (isset($existingProperty['is_featured'])) {
            $property['is_featured'] = $existingProperty['is_featured'];
        }
        $property['neighborhood_id'] = $existingProperty['neighborhood_id'];
        $property['property_type_id'] = $existingProperty['property_type_id'];
        $property['property_use_id'] = $existingProperty['property_use_id'];
        $property['property_state_id'] = $existingProperty['property_state_id'];
        $property['consignment_type_id'] = $existingProperty['consignment_type_id'];
        $property['advisor_id'] = $existingProperty['advisor_id']; // Preservar el advisor_id existente
        
        // Si la descripción corta está vacía, mantener la existente
        if (empty($property['short_description']) && !empty($existingProperty['short_description'])) {
            $property['short_description'] = $existingProperty['short_description'];
        }
        
        echo "Inmueble #{$property['ref']}: Preservando campos relacionales existentes\n";
    }
    
    /**
     * Establecer relaciones predeterminadas para un nuevo inmueble
     * 
     * @param array &$property Datos de la propiedad
     * @param string $tipo_inmueble_name Nombre del tipo de inmueble
     * @param string $uso_inmueble_name Nombre del uso del inmueble
     * @param string $estado_inmueble_name Nombre del estado del inmueble
     */
    private function setDefaultRelations(&$property, $tipo_inmueble_name, $uso_inmueble_name, $estado_inmueble_name) {
        // Buscar o crear la ciudad
        $ciudad_name = $property['ciudad'] ?? '';
        if (!empty($ciudad_name)) {
            // Normalizar el name de la ciudad (eliminar espacios extras)
            $ciudad_name_normalizado = trim($ciudad_name);
            
            // Primero buscar por name exacto
            $stmt = $this->db->prepare("SELECT id, name FROM cities WHERE name = ?");
            $stmt->execute([$ciudad_name_normalizado]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $property['city_id'] = $result['id'];
                echo "Inmueble #{$property['ref']}: Ciudad encontrada exacta: {$result['name']} (ID: {$property['city_id']})\n";
            } else {
                // Si la ciudad no existe, crearla
                try {
                    $stmt = $this->db->prepare("INSERT INTO cities (name) VALUES (?)");
                    $stmt->execute([$ciudad_name_normalizado]);
                    $property['city_id'] = $this->db->lastInsertId();
                    echo "Inmueble #{$property['ref']}: Ciudad creada: {$ciudad_name_normalizado} (ID: {$property['city_id']})\n";
                } catch (Exception $e) {
                    echo "Inmueble #{$property['ref']}: Error al crear ciudad: {$e->getMessage()}\n";
                    $property['city_id'] = 1; // ID predeterminado para ciudad
                }
            }
        } else {
            $property['city_id'] = 1; // ID predeterminado para ciudad
        }
        
        // Buscar o crear el barrio
        $barrio_name = $property['barrio'] ?? '';
        if (!empty($barrio_name)) {
            // Normalizar el name del barrio
            $barrio_name_normalizado = trim($barrio_name);
            
            // Buscar por name exacto y ciudad
            $stmt = $this->db->prepare("SELECT id, name FROM neighborhoods WHERE name = ? AND city_id = ?");
            $stmt->execute([$barrio_name_normalizado, $property['city_id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $property['neighborhood_id'] = $result['id'];
                echo "Inmueble #{$property['ref']}: Barrio encontrado exacto: {$result['name']} (ID: {$property['neighborhood_id']})\n";
            } else {
                // Si el barrio no existe, crearlo
                try {
                    $stmt = $this->db->prepare("INSERT INTO neighborhoods (name, city_id) VALUES (?, ?)");
                    $stmt->execute([$barrio_name_normalizado, $property['city_id']]);
                    $property['neighborhood_id'] = $this->db->lastInsertId();
                    echo "Inmueble #{$property['ref']}: Barrio creado: {$barrio_name_normalizado} (ID: {$property['neighborhood_id']})\n";
                } catch (Exception $e) {
                    echo "Inmueble #{$property['ref']}: Error al crear barrio: {$e->getMessage()}\n";
                    $property['neighborhood_id'] = 1; // ID predeterminado para barrio
                }
            }
        } else {
            $property['neighborhood_id'] = 1; // ID predeterminado para barrio
        }
        
        // Buscar o crear el tipo de inmueble
        if (!empty($tipo_inmueble_name)) {
            // Normalizar el name del tipo de inmueble
            $tipo_inmueble_normalizado = trim($tipo_inmueble_name);
            
            // Buscar por name exacto
            $stmt = $this->db->prepare("SELECT id, name FROM property_types WHERE name = ?");
            $stmt->execute([$tipo_inmueble_normalizado]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $property['property_type_id'] = $result['id'];
                echo "Inmueble #{$property['ref']}: Tipo de inmueble encontrado: {$result['name']} (ID: {$property['property_type_id']})\n";
            } else {
                // Si el tipo de inmueble no existe, crearlo
                try {
                    $stmt = $this->db->prepare("INSERT INTO property_types (name) VALUES (?)");
                    $stmt->execute([$tipo_inmueble_normalizado]);
                    $property['property_type_id'] = $this->db->lastInsertId();
                    echo "Inmueble #{$property['ref']}: Tipo de inmueble creado: {$tipo_inmueble_normalizado} (ID: {$property['property_type_id']})\n";
                } catch (Exception $e) {
                    echo "Inmueble #{$property['ref']}: Error al crear tipo de inmueble: {$e->getMessage()}\n";
                    $property['property_type_id'] = 1; // ID predeterminado para tipo de inmueble
                }
            }
        } else {
            $property['property_type_id'] = 1; // ID predeterminado para tipo de inmueble
        }
        
        // Buscar o crear el uso de inmueble
        if (!empty($uso_inmueble_name)) {
            // Normalizar el name del uso de inmueble
            $uso_inmueble_normalizado = trim($uso_inmueble_name);
            
            // Buscar por name exacto
            $stmt = $this->db->prepare("SELECT id, name FROM property_uses WHERE name = ?");
            $stmt->execute([$uso_inmueble_normalizado]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $property['property_use_id'] = $result['id'];
                $property['uso_id'] = $result['id']; // Para compatibilidad
                echo "Inmueble #{$property['ref']}: Uso de inmueble encontrado: {$result['name']} (ID: {$property['property_use_id']})\n";
            } else {
                // Si el uso de inmueble no existe, crearlo
                try {
                    $stmt = $this->db->prepare("INSERT INTO property_uses (name) VALUES (?)");
                    $stmt->execute([$uso_inmueble_normalizado]);
                    $property['property_use_id'] = $this->db->lastInsertId();
                    $property['uso_id'] = $property['property_use_id']; // Para compatibilidad
                    echo "Inmueble #{$property['ref']}: Uso de inmueble creado: {$uso_inmueble_normalizado} (ID: {$property['property_use_id']})\n";
                } catch (Exception $e) {
                    echo "Inmueble #{$property['ref']}: Error al crear uso de inmueble: {$e->getMessage()}\n";
                    $property['property_use_id'] = 1; // ID predeterminado para uso de inmueble
                    $property['uso_id'] = 1; // Para compatibilidad
                }
            }
        } else {
            $property['property_use_id'] = 1; // ID predeterminado para uso de inmueble
            $property['uso_id'] = 1; // Para compatibilidad
        }
        
        // Buscar o crear el estado de inmueble
        if (!empty($estado_inmueble_name)) {
            // Normalizar el name del estado de inmueble
            $estado_inmueble_normalizado = trim($estado_inmueble_name);
            
            // Buscar por name exacto
            $stmt = $this->db->prepare("SELECT id, name FROM property_states WHERE name = ?");
            $stmt->execute([$estado_inmueble_normalizado]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $property['property_state_id'] = $result['id'];
                $property['estado_actual_id'] = $result['id']; // Para compatibilidad
                echo "Inmueble #{$property['ref']}: Estado de inmueble encontrado: {$result['name']} (ID: {$property['property_state_id']})\n";
            } else {
                // Si el estado de inmueble no existe, crearlo
                try {
                    $stmt = $this->db->prepare("INSERT INTO property_states (name) VALUES (?)");
                    $stmt->execute([$estado_inmueble_normalizado]);
                    $property['property_state_id'] = $this->db->lastInsertId();
                    $property['estado_actual_id'] = $property['property_state_id']; // Para compatibilidad
                    echo "Inmueble #{$property['ref']}: Estado de inmueble creado: {$estado_inmueble_normalizado} (ID: {$property['property_state_id']})\n";
                } catch (Exception $e) {
                    echo "Inmueble #{$property['ref']}: Error al crear estado de inmueble: {$e->getMessage()}\n";
                    $property['property_state_id'] = 1; // ID predeterminado para estado de inmueble
                    $property['estado_actual_id'] = 1; // Para compatibilidad
                }
            }
        } else {
            $property['property_state_id'] = 1; // ID predeterminado para estado de inmueble
            $property['estado_actual_id'] = 1; // Para compatibilidad
        }
        
        // Gestionar el tipo de consignación
        $tipo_consignacion_name = $property['tipo_consignacion'] ?? '';
        if (!empty($tipo_consignacion_name)) {
            // Normalizar el name del tipo de consignación
            $tipo_consignacion_normalizado = trim($tipo_consignacion_name);
            
            // Buscar por name exacto
            $stmt = $this->db->prepare("SELECT id, name FROM consignment_types WHERE name = ?");
            $stmt->execute([$tipo_consignacion_normalizado]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $property['consignment_type_id'] = $result['id'];
                echo "Inmueble #{$property['ref']}: Tipo de consignación encontrado: {$result['name']} (ID: {$property['consignment_type_id']})\n";
            } else {
                // Si el tipo de consignación no existe, crearlo
                try {
                    $stmt = $this->db->prepare("INSERT INTO consignment_types (name) VALUES (?)");
                    $stmt->execute([$tipo_consignacion_normalizado]);
                    $property['consignment_type_id'] = $this->db->lastInsertId();
                    echo "Inmueble #{$property['ref']}: Tipo de consignación creado: {$tipo_consignacion_normalizado} (ID: {$property['consignment_type_id']})\n";
                } catch (Exception $e) {
                    echo "Inmueble #{$property['ref']}: Error al crear tipo de consignación: {$e->getMessage()}\n";
                    $property['consignment_type_id'] = 1; // ID predeterminado para tipo de consignación
                }
            }
        } else {
            $property['consignment_type_id'] = 1; // ID predeterminado para tipo de consignación
        }
        
        // Generar descripción corta a partir de la descripción completa
        $this->generateShortDescription($property);
        
        echo "Inmueble #{$property['ref']}: Asignando valores predeterminados para campos relacionales\n";
    }
    
    /**
     * Generar descripción corta para un inmueble
     * 
     * @param array &$property Datos de la propiedad
     */
    private function generateShortDescription(&$property) {
        if (!empty($property['description'])) {
            // Extraer las primeras 150 caracteres de la descripción
            $descriptionCorta = substr($property['description'], 0, 150);
            // Si cortamos en medio de una palabra, retroceder hasta el último espacio
            if (strlen($descriptionCorta) === 150 && strlen($property['description']) > 150) {
                $lastSpace = strrpos($descriptionCorta, ' ');
                if ($lastSpace > 100) { // Asegurarse de que no retrocedemos demasiado
                    $descriptionCorta = substr($descriptionCorta, 0, $lastSpace);
                }
                $descriptionCorta .= '...';
            }
            $property['short_description'] = $descriptionCorta;
        } elseif (!empty($property['title'])) {
            // Si no hay descripción, usar el título
            $property['short_description'] = $property['title'];
        } else {
            // Si no hay título ni descripción, usar un texto genérico
            $property['short_description'] = 'Propiedad inmobiliaria disponible';
        }
    }
    
    /**
     * Limpiar y validar precios para evitar errores en MySQL
     * 
     * @param array &$property Datos de la propiedad
     */
    private function cleanPrices(&$property) {
        // Validar precio de venta
        if (isset($property['sale_price'])) {
            // Solo validar que no sea negativo
            if ($property['sale_price'] < 0) {
                echo "Inmueble #{$property['ref']}: Precio de venta negativo, ajustando a 0\n";
                $property['sale_price'] = 0;
            }
        } else {
            $property['sale_price'] = 0;
        }
        
        // Validar precio de arriendo
        if (isset($property['rent_price'])) {
            if ($property['rent_price'] > 999999999) {
                echo "Inmueble #{$property['ref']}: Precio de arriendo excede el rango permitido, ajustando\n";
                $property['rent_price'] = 999999999;
            } elseif ($property['rent_price'] < 0) {
                echo "Inmueble #{$property['ref']}: Precio de arriendo negativo, ajustando a 0\n";
                $property['rent_price'] = 0;
            }
        } else {
            $property['rent_price'] = 0;
        }
        
        // Validar administración
        if (isset($property['administration_fee'])) {
            if ($property['administration_fee'] > 9999999) {
                echo "Inmueble #{$property['ref']}: Administración excede el rango permitido, ajustando\n";
                $property['administration_fee'] = 9999999;
            } elseif ($property['administration_fee'] < 0) {
                echo "Inmueble #{$property['ref']}: Administración negativa, ajustando a 0\n";
                $property['administration_fee'] = 0;
            }
        } else {
            $property['administration_fee'] = 0;
        }
    }
    
    /**
     * Calcular hash MD5 de los datos de un inmueble
     * 
     * @param array $property Datos de la propiedad
     * @return string Hash MD5 de los datos
     */
    private function calculateDataHash($property) {
        // Crear una copia de la propiedad para calcular el hash
        $propertyForHash = $property;
        
        // Eliminar campos que no deben afectar al hash (campos que pueden cambiar sin que se considere un cambio real)
        unset($propertyForHash['id']);
        unset($propertyForHash['created_at']);
        unset($propertyForHash['updated_at']);
        unset($propertyForHash['data_hash']);
        unset($propertyForHash['imagenes']); // Las imágenes tienen su propio hash
        
        // Ordenar por clave para asegurar consistencia
        ksort($propertyForHash);
        
        // Serializar y calcular hash
        $serialized = json_encode($propertyForHash);
        return md5($serialized);
    }
    
    /**
     * Generar slug para un inmueble
     * 
     * @param string $codigoSincronizacion Código de sincronización
     * @param int $id ID del inmueble
     * @return string Slug generado
     */
    private function generateSlug($codigoSincronizacion, $id) {
        if (!empty($codigoSincronizacion) && trim($codigoSincronizacion) !== '') {
            return "inmueble-{$codigoSincronizacion}";
        } elseif (!empty($id)) {
            // Usar el formato inmueble-sin-codigo-sincronizacion-{ref} cuando no hay código de sincronización
            return "inmueble-sin-codigo-sincronizacion-{$id}";
        }
        return null; // Si no hay código ni ID, retornar null (se generará después de la inserción)
    }
    
    /**
     * Procesar un inmueble existente
     * 
     * @param array $property Datos del inmueble
     * @param array $existingProperty Datos existentes del inmueble
     * @param string $nuevoHash Nuevo hash de los datos
     */
    private function processExistingProperty($property, $existingProperty, $nuevoHash) {
        $propertyId = $existingProperty['id'];
        $propertyRef = $property['ref']; // Usamos ref como identificador interno, pero sync_code para la BD
        
        // Verificar si hay cambios comparando el hash
        if ($existingProperty['data_hash'] === $nuevoHash) {
            echo "Inmueble #{$propertyRef}: No hay cambios en los datos\n";
            $this->stats['inmuebles_sin_cambios']++;
            return;
        }
        
        echo "Inmueble #{$propertyRef}: Actualizando datos...\n";
        
        // Registrar cambio si está habilitado el seguimiento
        if ($this->trackChanges) {
            $this->registerChange($propertyId, $existingProperty, $property);
        }
        
        // Generar el slug si no existe o si ha cambiado el código de sincronización
        $slug = $existingProperty['slug'];
        if (empty($slug) || (isset($property['sync_code']) && $property['sync_code'] !== ($existingProperty['sync_code'] ?? ''))) {
            $slug = $this->generateSlug($property['sync_code'] ?? '', $propertyId);
            echo "Inmueble #{$propertyRef}: Generando slug: {$slug}\n";
        }
        
        // Preparar consulta de actualización
        $updateFields = [
            'ref' => $property['ref'] ?? '',
            'sync_code' => $property['sync_code'] ?? '',
            'title' => $property['title'] ?? '',
            'description' => $property['description'] ?? '',
            'short_description' => $property['short_description'] ?? '',
            'address' => $property['address'] ?? '',
            'sale_price' => $property['sale_price'] ?? 0,
            'rent_price' => $property['rent_price'] ?? 0,
            'administration_fee' => $property['administration_fee'] ?? 0,
            // 'total_price' eliminado (no existe en la tabla properties según convenciones Laravel)
            'built_area' => $property['built_area'] ?? 0,
            'private_area' => $property['private_area'] ?? 0,
            'total_area' => $property['total_area'] ?? 0,
            'land_area' => $property['land_area'] ?? 0,
            'bedrooms' => $property['bedrooms'] ?? 0,
            'bathrooms' => $property['bathrooms'] ?? 0,
            'garages' => $property['garages'] ?? 0,
            'stratum' => $property['stratum'] ?? 0,
            'age' => $property['age'] ?? 0,
            'floor' => $property['floor'] ?? 0,
            'has_elevator' => $property['has_elevator'] ?? 0,
            'is_featured' => $property['is_featured'] ?? 0,
            'is_active' => $property['is_active'] ?? 0,
            'is_hot' => $property['is_hot'] ?? 0,
            'latitude' => $property['latitude'] ?? 0,
            'longitude' => $property['longitude'] ?? 0,
            'city_id' => $property['city_id'] ?? 1,
            'neighborhood_id' => $property['neighborhood_id'] ?? 1,
            'property_type_id' => $property['property_type_id'] ?? 1,
            'property_use_id' => $property['property_use_id'] ?? 1,
            'uso_id' => $property['uso_id'] ?? 1, // Para compatibilidad
            'property_state_id' => $property['property_state_id'] ?? 1,
            'estado_actual_id' => $property['estado_actual_id'] ?? 1, // Para compatibilidad
            'consignment_type_id' => $property['consignment_type_id'] ?? 1,
            'advisor_id' => $property['advisor_id'] ?? 1,
            'data_hash' => $nuevoHash,
            'slug' => $slug,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // Construir la consulta SQL
        $updateQuery = "UPDATE properties SET ";
        $updateParts = [];
        $updateValues = [];
        
        foreach ($updateFields as $field => $value) {
            $updateParts[] = "{$field} = ?";
            $updateValues[] = $value;
        }
        
        $updateQuery .= implode(', ', $updateParts);
        $updateQuery .= " WHERE id = ?";
        $updateValues[] = $propertyId;
        
        // Ejecutar la actualización
        $stmt = $this->db->prepare($updateQuery);
        $result = $stmt->execute($updateValues);
        
        if ($result) {
            echo "Inmueble #{$propertyRef}: Actualizado correctamente\n";
            $this->stats['inmuebles_actualizados']++;
        } else {
            echo "Inmueble #{$propertyRef}: Error al actualizar: " . implode(', ', $stmt->errorInfo()) . "\n";
            $this->stats['errores']++;
        }
    }
    
    /**
     * Procesar un nuevo inmueble
     * 
     * @param array $property Datos del inmueble
     * @param string $nuevoHash Hash de los datos
     * @return int|null ID de la propiedad creada o null si hubo un error
     */
    private function processNewProperty($property, $nuevoHash) {
        $propertyRef = $property['ref']; // Usamos ref como identificador interno, pero sync_code para la BD
        
        echo "Inmueble #{$propertyRef}: Creando nuevo registro...\n";
        
        // Verificar si hay estados guardados para este inmueble
        $estadosGuardados = null;
        if (!empty($property['sync_code'])) {
            $stmt = $this->db->prepare("SELECT * FROM property_states WHERE property_id = ? AND sync_code = ?");
            $stmt->execute([$propertyRef, $property['sync_code']]);
            $estadosGuardados = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        // Usar estados guardados o valores por defecto
        $valorActivo = $estadosGuardados ? $estadosGuardados['is_active'] : 1;
        $valorDestacado = $estadosGuardados ? $estadosGuardados['is_featured'] : 0;
        $valorEnCaliente = $estadosGuardados ? $estadosGuardados['is_hot'] : 0;
        
        // Generar slug inicial si es posible
        $initialSlug = $this->generateSlug($property['sync_code'] ?? '', null);
        
        // Preparar campos para la inserción
        $insertFields = [
            'ref' => $property['ref'] ?? '',
            'sync_code' => $property['sync_code'] ?? '',
            'title' => $property['title'] ?? '',
            'description' => $property['description'] ?? '',
            'short_description' => $property['short_description'] ?? '',
            'address' => $property['address'] ?? '',
            'sale_price' => $property['sale_price'] ?? 0,
            'rent_price' => $property['rent_price'] ?? 0,
            'administration_fee' => $property['administration_fee'] ?? 0,
            // 'total_price' eliminado (no existe en la tabla properties según convenciones Laravel)
            'built_area' => $property['built_area'] ?? 0,
            'private_area' => $property['private_area'] ?? 0,
            'total_area' => $property['total_area'] ?? 0,
            'land_area' => $property['land_area'] ?? 0,
            'bedrooms' => $property['bedrooms'] ?? 0,
            'bathrooms' => $property['bathrooms'] ?? 0,
            'garages' => $property['garages'] ?? 0,
            'stratum' => $property['stratum'] ?? 0,
            'age' => $property['age'] ?? 0,
            'floor' => $property['floor'] ?? 0,
            'has_elevator' => $property['has_elevator'] ?? 0,
            'is_featured' => $valorDestacado,
            'is_active' => $valorActivo,
            'is_hot' => $valorEnCaliente,
            'latitude' => $property['latitude'] ?? 0,
            'longitude' => $property['longitude'] ?? 0,
            'city_id' => $property['city_id'] ?? 1,
            'neighborhood_id' => $property['neighborhood_id'] ?? 1,
            'property_type_id' => $property['property_type_id'] ?? 1,
            'property_use_id' => $property['property_use_id'] ?? 1,
            'uso_id' => $property['uso_id'] ?? 1, // Para compatibilidad
            'property_state_id' => $property['property_state_id'] ?? 1,
            'estado_actual_id' => $property['estado_actual_id'] ?? 1, // Para compatibilidad
            'consignment_type_id' => $property['consignment_type_id'] ?? 1,
            'advisor_id' => $property['advisor_id'] ?? 1,
            'data_hash' => $nuevoHash,
            'slug' => $initialSlug,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // Construir la consulta SQL
        $fields = array_keys($insertFields);
        $placeholders = array_fill(0, count($fields), '?');
        
        $insertQuery = "INSERT INTO properties (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        // Ejecutar la inserción
        $stmt = $this->db->prepare($insertQuery);
        $result = $stmt->execute(array_values($insertFields));
        
        if ($result) {
            $propertyId = $this->db->lastInsertId();
            $property['id'] = $propertyId;
            
            // Si no se pudo generar el slug inicialmente porque no había código de sincronización,
            // ahora podemos generarlo con el ID
            if (!$initialSlug) {
                $slugWithId = $this->generateSlug(null, $propertyId);
                echo "Inmueble #{$propertyRef}: Actualizando slug con ID: {$slugWithId}\n";
                $updateSlugStmt = $this->db->prepare("UPDATE properties SET slug = ? WHERE id = ?");
                $updateSlugStmt->execute([$slugWithId, $propertyId]);
            }
            
            // Si hay estados guardados, actualizar la tabla property_states
            if ($estadosGuardados) {
                echo "Inmueble #{$propertyRef}: Actualizando estados guardados\n";
                $updateEstadosStmt = $this->db->prepare("
                    UPDATE property_states 
                    SET is_active = ?, is_featured = ?, is_hot = ?, updated_at = NOW() 
                    WHERE property_id = ? AND sync_code = ?
                ");
                $updateEstadosStmt->execute([
                    $valorActivo, 
                    $valorDestacado, 
                    $valorEnCaliente, 
                    $propertyRef, 
                    $property['sync_code'] ?? ''
                ]);
            }
            
            echo "Inmueble #{$propertyRef}: Creado correctamente con ID {$propertyId}\n";
            $this->stats['inmuebles_nuevos']++;
            return $propertyId; // Devolver el ID de la propiedad creada
        } else {
            echo "Inmueble #{$propertyRef}: Error al crear: " . implode(', ', $stmt->errorInfo()) . "\n";
            $this->stats['errores']++;
            return null; // Devolver null si hubo un error
        }
    }
    
    /**
     * Registrar un cambio en un inmueble
     * 
     * @param int $propertyId ID del inmueble
     * @param array $oldData Datos antiguos
     * @param array $newData Datos nuevos
     */
    private function registerChange($propertyId, $oldData, $newData) {
        // Verificar si existe la tabla de cambios
        $tableExists = $this->db->query("SHOW TABLES LIKE 'property_changes'")->rowCount() > 0;
        
        if (!$tableExists) {
            // Crear la tabla si no existe
            $this->db->exec("
                CREATE TABLE property_changes (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    property_id INT NOT NULL,
                    campo VARCHAR(50) NOT NULL,
                    valor_anterior TEXT,
                    valor_nuevo TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX (property_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
        
        // Campos a monitorear para cambios
        $fieldsToTrack = [
            'title', 'description', 'address', 'sale_price', 'rent_price',
            'built_area', 'private_area', 'total_area', 'bedrooms', 'bathrooms',
            'garages', 'stratum', 'age', 'floor', 'has_elevator', 'administration_fee',
            'latitude', 'longitude', 'city_id', 'neighborhood_id', 'property_type_id',
            'property_use_id', 'property_state_id', 'consignment_type_id'
        ];
        
        // Registrar cambios
        foreach ($fieldsToTrack as $field) {
            if (isset($oldData[$field], $newData[$field]) && $oldData[$field] != $newData[$field]) {
                $stmt = $this->db->prepare("
                    INSERT INTO property_changes (property_id, campo, valor_anterior, valor_nuevo)
                    VALUES (?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $propertyId,
                    $field,
                    $oldData[$field],
                    $newData[$field]
                ]);
            }
        }
    }
    
    /**
     * Procesa las características de un inmueble
     * 
     * @param int $propertyId ID del inmueble
     * @param array $characteristics Array de características
     */
    private function processCharacteristics($propertyId, $characteristics) {
        
        // Verificar si existen las tablas necesarias
        $characteristicsExists = $this->db->query("SHOW TABLES LIKE 'characteristics'")->rowCount() > 0;
        $propertyCharacteristicsExists = $this->db->query("SHOW TABLES LIKE 'property_characteristics'")->rowCount() > 0;
        
        if (!$characteristicsExists || !$propertyCharacteristicsExists) {
            echo "Inmueble #{$propertyId}: No se pueden procesar características - faltan tablas necesarias\n";
            return;
        }
        
        // Eliminar características existentes para este inmueble
        $stmt = $this->db->prepare("DELETE FROM property_characteristics WHERE property_id = ?");
        $stmt->execute([$propertyId]);
        
        // Preparar consultas
        $findCharacteristicStmt = $this->db->prepare("SELECT id FROM characteristics WHERE name = ? LIMIT 1");
        $insertCharacteristicStmt = $this->db->prepare("INSERT INTO characteristics (name, created_at, updated_at) VALUES (?, NOW(), NOW())");
        $insertRelationStmt = $this->db->prepare("
            INSERT INTO property_characteristics (property_id, characteristic_id, value, is_numeric, created_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
        ");
        
        $characteristicsCount = 0;
        
        foreach ($characteristics as $characteristic) {
            $name = $characteristic['nombre']; // El nombre viene en español desde la API
            $value = $characteristic['valor'] ?? '';
            
            // Buscar si la característica ya existe
            $findCharacteristicStmt->execute([$name]);
            $characteristicRow = $findCharacteristicStmt->fetch();
            
            if ($characteristicRow) {
                // Usar característica existente
                $characteristicId = $characteristicRow['id'];
            } else {
                // Crear nueva característica
                $insertCharacteristicStmt->execute([$name]);
                $characteristicId = $this->db->lastInsertId();
            }
            
            // Determinar si el valor es numérico
            $isNumeric = 0; // Por defecto, asumimos que no es numérico
            
            // Verificar si el valor es numérico (entero o decimal)
            if (is_numeric($value)) {
                $isNumeric = 1;
            }
            
            // Crear relación entre inmueble y característica
            $insertRelationStmt->execute([$propertyId, $characteristicId, $value, $isNumeric]);
            $characteristicsCount++;
        }
        
        echo "Inmueble #$propertyId: $characteristicsCount características procesadas\n";
    }
    
    /**
     * Procesar las imágenes de un inmueble
     * 
     * @param array $property Datos del inmueble
     */
    private function processPropertyImages($property) {
        if (empty($property['id'])) {
            echo "Inmueble #{$property['ref']}: No se puede procesar imágenes sin ID de inmueble\n";
            return;
        }
        
        // Verificar si hay imágenes para procesar
        $hasImages = !empty($property['imagenes']) && is_array($property['imagenes']);
        
        // Cargar las clases necesarias si no están cargadas
        if (!class_exists('ImageProcessorLaravel')) {
            require_once __DIR__ . '/ImageProcessorLaravel.php';
        }
        
        if (!class_exists('ImageSynchronizer')) {
            require_once __DIR__ . '/ImageSynchronizer.php';
        }
        
        // Obtener modo de almacenamiento de imágenes (local o laravel)
        $storageMode = getenv('IMAGES_STORAGE_MODE') ?: 'laravel';
        
        // Crear instancia del procesador de imágenes con soporte para Laravel
        $imageProcessor = new ImageProcessorLaravel(
            $this->db, 
            $this->imagesFolder, 
            $this->stats,
            $this->downloadImages,
            $storageMode,
            'public',
            'images/inmuebles'
        );
        
        // Crear instancia del sincronizador de imágenes
        $imageSynchronizer = new ImageSynchronizer(
            $this->db,
            $this->imagesFolder,
            $imageProcessor,
            $this->stats
        );
        
        // Sincronizar imágenes (añadir nuevas, actualizar modificadas, eliminar obsoletas)
        if ($hasImages) {
            echo "Inmueble #{$property['ref']}: Sincronizando " . count($property['imagenes']) . " imágenes...\n";
            $result = $imageSynchronizer->synchronizeImages($property['id'], $property['ref'], $property['imagenes']);
            
            echo "Inmueble #{$property['ref']}: Sincronización de imágenes completada - ";
            echo "{$result['added']} añadidas, {$result['updated']} actualizadas, {$result['deleted']} eliminadas\n";
        } else {
            echo "Inmueble #{$property['ref']}: No hay imágenes para procesar\n";
            
            // Si no hay imágenes en la API pero podría haber imágenes existentes, eliminarlas
            $result = $imageSynchronizer->synchronizeImages($property['id'], $property['ref'], []);
            
            if ($result['deleted'] > 0) {
                echo "Inmueble #{$property['ref']}: Se eliminaron {$result['deleted']} imágenes obsoletas\n";
            }
        }
    }
}

// ... (rest of the code remains the same)
