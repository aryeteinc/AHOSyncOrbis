<?php
/**
 * Configuración de mapeo de esquemas de base de datos
 * Este archivo permite definir diferentes esquemas de nombres para tablas y columnas
 */

return [
    // Esquema actual a utilizar (laravel, spanish, custom)
    'current_schema' => 'laravel',
    
    // Definición de esquemas disponibles
    'schemas' => [
        // Esquema Laravel (nombres en inglés, plural para tablas, convenciones Laravel)
        'laravel' => [
            'tables' => [
                'properties' => [
                    'spanish_name' => 'inmuebles',
                    'columns' => [
                        'id' => ['type' => 'INT', 'auto_increment' => true, 'primary' => true],
                        'ref' => ['type' => 'VARCHAR(50)', 'spanish' => 'referencia'],
                        'sync_code' => ['type' => 'VARCHAR(100)', 'spanish' => 'codigo_sincronizacion'],
                        'title' => ['type' => 'VARCHAR(255)', 'spanish' => 'titulo'],
                        'description' => ['type' => 'TEXT', 'spanish' => 'descripcion'],
                        'short_description' => ['type' => 'TEXT', 'spanish' => 'description_corta'],
                        'address' => ['type' => 'VARCHAR(255)', 'spanish' => 'direccion'],
                        'sale_price' => ['type' => 'DECIMAL(15,2)', 'spanish' => 'precio_venta'],
                        'rent_price' => ['type' => 'DECIMAL(15,2)', 'spanish' => 'precio_arriendo'],
                        'built_area' => ['type' => 'DECIMAL(10,2)', 'spanish' => 'area_construida'],
                        'private_area' => ['type' => 'DECIMAL(10,2)', 'spanish' => 'area_privada'],
                        'total_area' => ['type' => 'DECIMAL(10,2)', 'spanish' => 'area_total'],
                        'bedrooms' => ['type' => 'INT', 'spanish' => 'habitaciones'],
                        'bathrooms' => ['type' => 'INT', 'spanish' => 'banos'],
                        'garages' => ['type' => 'INT', 'spanish' => 'garajes'],
                        'stratum' => ['type' => 'INT', 'spanish' => 'estrato'],
                        'age' => ['type' => 'INT', 'spanish' => 'antiguedad'],
                        'floor' => ['type' => 'INT', 'spanish' => 'piso'],
                        'has_elevator' => ['type' => 'TINYINT(1)', 'spanish' => 'tiene_ascensor'],
                        'administration_fee' => ['type' => 'DECIMAL(15,2)', 'spanish' => 'administracion'],
                        'latitude' => ['type' => 'DECIMAL(10,8)', 'spanish' => 'latitud'],
                        'longitude' => ['type' => 'DECIMAL(11,8)', 'spanish' => 'longitud'],
                        'city_id' => ['type' => 'INT', 'spanish' => 'ciudad_id'],
                        'neighborhood_id' => ['type' => 'INT', 'spanish' => 'barrio_id'],
                        'property_type_id' => ['type' => 'INT', 'spanish' => 'tipo_inmueble_id'],
                        'property_use_id' => ['type' => 'INT', 'spanish' => 'uso_inmueble_id'],
                        'property_status_id' => ['type' => 'INT', 'spanish' => 'estado_inmueble_id'],
                        'consignment_type_id' => ['type' => 'INT', 'spanish' => 'tipo_consignacion_id'],
                        'advisor_id' => ['type' => 'INT', 'spanish' => 'asesor_id'],
                        'is_featured' => ['type' => 'TINYINT(1)', 'spanish' => 'destacado'],
                        'is_active' => ['type' => 'TINYINT(1)', 'spanish' => 'activo'],
                        'is_hot' => ['type' => 'TINYINT(1)', 'spanish' => 'en_caliente'],
                        'data_hash' => ['type' => 'VARCHAR(32)', 'spanish' => 'hash_datos'],
                        'slug' => ['type' => 'VARCHAR(255)', 'spanish' => 'slug'],
                        'created_at' => ['type' => 'TIMESTAMP', 'spanish' => 'fecha_creacion'],
                        'updated_at' => ['type' => 'TIMESTAMP', 'spanish' => 'fecha_actualizacion'],
                        // Campos de compatibilidad
                        'uso_id' => ['type' => 'INT', 'compatibility' => true],
                        'estado_actual_id' => ['type' => 'INT', 'compatibility' => true]
                    ]
                ],
                'images' => [
                    'spanish_name' => 'imagenes',
                    'columns' => [
                        'id' => ['type' => 'INT', 'auto_increment' => true, 'primary' => true],
                        'property_id' => ['type' => 'INT', 'spanish' => 'inmueble_id'],
                        'filename' => ['type' => 'VARCHAR(255)', 'spanish' => 'nombre_archivo'],
                        'url' => ['type' => 'VARCHAR(255)', 'spanish' => 'url'],
                        'is_main' => ['type' => 'TINYINT(1)', 'spanish' => 'es_principal'],
                        'order' => ['type' => 'INT', 'spanish' => 'orden'],
                        'data_hash' => ['type' => 'VARCHAR(32)', 'spanish' => 'hash_datos'],
                        'created_at' => ['type' => 'TIMESTAMP', 'spanish' => 'fecha_creacion'],
                        'updated_at' => ['type' => 'TIMESTAMP', 'spanish' => 'fecha_actualizacion']
                    ]
                ],
                'property_changes' => [
                    'spanish_name' => 'cambios_inmuebles',
                    'columns' => [
                        'id' => ['type' => 'INT', 'auto_increment' => true, 'primary' => true],
                        'property_id' => ['type' => 'INT', 'spanish' => 'inmueble_id'],
                        'field_name' => ['type' => 'VARCHAR(50)', 'spanish' => 'nombre_campo'],
                        'old_value' => ['type' => 'TEXT', 'spanish' => 'valor_anterior'],
                        'new_value' => ['type' => 'TEXT', 'spanish' => 'valor_nuevo'],
                        'created_at' => ['type' => 'TIMESTAMP', 'spanish' => 'fecha_creacion']
                    ]
                ],
                'property_states' => [
                    'spanish_name' => 'estados_inmuebles',
                    'columns' => [
                        'id' => ['type' => 'INT', 'auto_increment' => true, 'primary' => true],
                        'property_ref' => ['type' => 'VARCHAR(50)', 'spanish' => 'referencia_inmueble'],
                        'sync_code' => ['type' => 'VARCHAR(100)', 'spanish' => 'codigo_sincronizacion'],
                        'is_active' => ['type' => 'TINYINT(1)', 'spanish' => 'activo'],
                        'is_featured' => ['type' => 'TINYINT(1)', 'spanish' => 'destacado'],
                        'is_hot' => ['type' => 'TINYINT(1)', 'spanish' => 'en_caliente'],
                        'last_sync' => ['type' => 'TIMESTAMP', 'spanish' => 'ultima_sincronizacion'],
                        'created_at' => ['type' => 'TIMESTAMP', 'spanish' => 'fecha_creacion'],
                        'updated_at' => ['type' => 'TIMESTAMP', 'spanish' => 'fecha_actualizacion']
                    ]
                ],
                'cities' => [
                    'spanish_name' => 'ciudades',
                    'columns' => [
                        'id' => ['type' => 'INT', 'auto_increment' => true, 'primary' => true],
                        'name' => ['type' => 'VARCHAR(100)', 'spanish' => 'nombre'],
                        'created_at' => ['type' => 'TIMESTAMP', 'spanish' => 'fecha_creacion'],
                        'updated_at' => ['type' => 'TIMESTAMP', 'spanish' => 'fecha_actualizacion']
                    ]
                ],
                'neighborhoods' => [
                    'spanish_name' => 'barrios',
                    'columns' => [
                        'id' => ['type' => 'INT', 'auto_increment' => true, 'primary' => true],
                        'name' => ['type' => 'VARCHAR(100)', 'spanish' => 'nombre'],
                        'city_id' => ['type' => 'INT', 'spanish' => 'ciudad_id'],
                        'created_at' => ['type' => 'TIMESTAMP', 'spanish' => 'fecha_creacion'],
                        'updated_at' => ['type' => 'TIMESTAMP', 'spanish' => 'fecha_actualizacion']
                    ]
                ],
                'property_types' => [
                    'spanish_name' => 'tipos_inmuebles',
                    'columns' => [
                        'id' => ['type' => 'INT', 'auto_increment' => true, 'primary' => true],
                        'name' => ['type' => 'VARCHAR(100)', 'spanish' => 'nombre'],
                        'created_at' => ['type' => 'TIMESTAMP', 'spanish' => 'fecha_creacion'],
                        'updated_at' => ['type' => 'TIMESTAMP', 'spanish' => 'fecha_actualizacion']
                    ]
                ],
                'property_uses' => [
                    'spanish_name' => 'usos_inmuebles',
                    'columns' => [
                        'id' => ['type' => 'INT', 'auto_increment' => true, 'primary' => true],
                        'name' => ['type' => 'VARCHAR(100)', 'spanish' => 'nombre'],
                        'created_at' => ['type' => 'TIMESTAMP', 'spanish' => 'fecha_creacion'],
                        'updated_at' => ['type' => 'TIMESTAMP', 'spanish' => 'fecha_actualizacion']
                    ]
                ],
                'property_statuses' => [
                    'spanish_name' => 'estados_inmuebles',
                    'columns' => [
                        'id' => ['type' => 'INT', 'auto_increment' => true, 'primary' => true],
                        'name' => ['type' => 'VARCHAR(100)', 'spanish' => 'nombre'],
                        'created_at' => ['type' => 'TIMESTAMP', 'spanish' => 'fecha_creacion'],
                        'updated_at' => ['type' => 'TIMESTAMP', 'spanish' => 'fecha_actualizacion']
                    ]
                ],
                'consignment_types' => [
                    'spanish_name' => 'tipos_consignacion',
                    'columns' => [
                        'id' => ['type' => 'INT', 'auto_increment' => true, 'primary' => true],
                        'name' => ['type' => 'VARCHAR(100)', 'spanish' => 'nombre'],
                        'created_at' => ['type' => 'TIMESTAMP', 'spanish' => 'fecha_creacion'],
                        'updated_at' => ['type' => 'TIMESTAMP', 'spanish' => 'fecha_actualizacion']
                    ]
                ],
                'advisors' => [
                    'spanish_name' => 'asesores',
                    'columns' => [
                        'id' => ['type' => 'INT', 'auto_increment' => true, 'primary' => true],
                        'name' => ['type' => 'VARCHAR(100)', 'spanish' => 'nombre'],
                        'email' => ['type' => 'VARCHAR(100)', 'spanish' => 'correo'],
                        'phone' => ['type' => 'VARCHAR(50)', 'spanish' => 'telefono'],
                        'is_active' => ['type' => 'TINYINT(1)', 'spanish' => 'activo'],
                        'created_at' => ['type' => 'TIMESTAMP', 'spanish' => 'fecha_creacion'],
                        'updated_at' => ['type' => 'TIMESTAMP', 'spanish' => 'fecha_actualizacion']
                    ]
                ],
                'characteristics' => [
                    'spanish_name' => 'caracteristicas',
                    'columns' => [
                        'id' => ['type' => 'INT', 'auto_increment' => true, 'primary' => true],
                        'name' => ['type' => 'VARCHAR(100)', 'spanish' => 'nombre'],
                        'category' => ['type' => 'VARCHAR(50)', 'spanish' => 'categoria'],
                        'created_at' => ['type' => 'TIMESTAMP', 'spanish' => 'fecha_creacion'],
                        'updated_at' => ['type' => 'TIMESTAMP', 'spanish' => 'fecha_actualizacion']
                    ]
                ],
                'property_characteristics' => [
                    'spanish_name' => 'inmuebles_caracteristicas',
                    'columns' => [
                        'id' => ['type' => 'INT', 'auto_increment' => true, 'primary' => true],
                        'property_id' => ['type' => 'INT', 'spanish' => 'inmueble_id'],
                        'characteristic_id' => ['type' => 'INT', 'spanish' => 'caracteristica_id'],
                        'created_at' => ['type' => 'TIMESTAMP', 'spanish' => 'fecha_creacion']
                    ]
                ]
            ]
        ],
        
        // Esquema en español (nombres en español, singular para tablas)
        'spanish' => [
            'tables' => [
                'inmuebles' => [
                    'english_name' => 'properties',
                    'columns' => [
                        'id' => ['type' => 'INT', 'auto_increment' => true, 'primary' => true],
                        'referencia' => ['type' => 'VARCHAR(50)', 'english' => 'ref'],
                        'codigo_sincronizacion' => ['type' => 'VARCHAR(100)', 'english' => 'sync_code'],
                        'titulo' => ['type' => 'VARCHAR(255)', 'english' => 'title'],
                        'descripcion' => ['type' => 'TEXT', 'english' => 'description'],
                        'descripcion_corta' => ['type' => 'TEXT', 'english' => 'short_description'],
                        'direccion' => ['type' => 'VARCHAR(255)', 'english' => 'address'],
                        'precio_venta' => ['type' => 'DECIMAL(15,2)', 'english' => 'sale_price'],
                        'precio_arriendo' => ['type' => 'DECIMAL(15,2)', 'english' => 'rent_price'],
                        'area_construida' => ['type' => 'DECIMAL(10,2)', 'english' => 'built_area'],
                        'area_privada' => ['type' => 'DECIMAL(10,2)', 'english' => 'private_area'],
                        'area_total' => ['type' => 'DECIMAL(10,2)', 'english' => 'total_area'],
                        'habitaciones' => ['type' => 'INT', 'english' => 'bedrooms'],
                        'banos' => ['type' => 'INT', 'english' => 'bathrooms'],
                        'garajes' => ['type' => 'INT', 'english' => 'garages'],
                        'estrato' => ['type' => 'INT', 'english' => 'stratum'],
                        'antiguedad' => ['type' => 'INT', 'english' => 'age'],
                        'piso' => ['type' => 'INT', 'english' => 'floor'],
                        'tiene_ascensor' => ['type' => 'TINYINT(1)', 'english' => 'has_elevator'],
                        'administracion' => ['type' => 'DECIMAL(15,2)', 'english' => 'administration_fee'],
                        'latitud' => ['type' => 'DECIMAL(10,8)', 'english' => 'latitude'],
                        'longitud' => ['type' => 'DECIMAL(11,8)', 'english' => 'longitude'],
                        'ciudad_id' => ['type' => 'INT', 'english' => 'city_id'],
                        'barrio_id' => ['type' => 'INT', 'english' => 'neighborhood_id'],
                        'tipo_inmueble_id' => ['type' => 'INT', 'english' => 'property_type_id'],
                        'uso_inmueble_id' => ['type' => 'INT', 'english' => 'property_use_id'],
                        'estado_inmueble_id' => ['type' => 'INT', 'english' => 'property_status_id'],
                        'tipo_consignacion_id' => ['type' => 'INT', 'english' => 'consignment_type_id'],
                        'asesor_id' => ['type' => 'INT', 'english' => 'advisor_id'],
                        'destacado' => ['type' => 'TINYINT(1)', 'english' => 'is_featured'],
                        'activo' => ['type' => 'TINYINT(1)', 'english' => 'is_active'],
                        'en_caliente' => ['type' => 'TINYINT(1)', 'english' => 'is_hot'],
                        'hash_datos' => ['type' => 'VARCHAR(32)', 'english' => 'data_hash'],
                        'slug' => ['type' => 'VARCHAR(255)', 'english' => 'slug'],
                        'fecha_creacion' => ['type' => 'TIMESTAMP', 'english' => 'created_at'],
                        'fecha_actualizacion' => ['type' => 'TIMESTAMP', 'english' => 'updated_at']
                    ]
                ]
                // Otras tablas en español se definirían aquí...
            ]
        ],
        
        // Puedes agregar más esquemas personalizados aquí
        'custom' => [
            'tables' => [
                // Definir tablas y columnas personalizadas aquí
            ]
        ]
    ]
];
