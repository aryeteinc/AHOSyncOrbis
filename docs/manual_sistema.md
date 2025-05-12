# Documentación del Sistema de Sincronización de Propiedades Inmobiliarias (SyncOrbisPhp)

## Índice
1. [Introducción](#introducción)
2. [Estructura de la Base de Datos](#estructura-de-la-base-de-datos)
3. [Gestión de Imágenes Destacadas](#gestión-de-imágenes-destacadas)
4. [Sistema de Exclusión de Propiedades](#sistema-de-exclusión-de-propiedades)
5. [Comandos Disponibles](#comandos-disponibles)
6. [Flujo de Sincronización](#flujo-de-sincronización)
7. [Solución de Problemas](#solución-de-problemas)

## Introducción

El sistema SyncOrbisPhp es una herramienta diseñada para sincronizar datos de propiedades inmobiliarias desde una API externa a una base de datos local. El sistema maneja la sincronización de datos básicos de las propiedades, características, imágenes y relaciones con otras entidades como ciudades, barrios, tipos de propiedades, etc.

El sistema está diseñado siguiendo las convenciones de Laravel para nombres de tablas y columnas, utilizando nombres en inglés y plural para las tablas (ej: properties, images, advisors), claves foráneas con el formato tabla_singular_id (ej: property_id, city_id), y columnas booleanas con el prefijo is_ (ej: is_active, is_featured).

## Estructura de la Base de Datos

El sistema utiliza las siguientes tablas principales:

1. **properties**: Almacena los datos básicos de las propiedades inmobiliarias.
2. **images**: Almacena las imágenes asociadas a cada propiedad.
3. **property_characteristics**: Relaciona propiedades con sus características.
4. **characteristics**: Catálogo de características disponibles.
5. **cities**: Catálogo de ciudades.
6. **neighborhoods**: Catálogo de barrios.
7. **property_types**: Catálogo de tipos de propiedades.
8. **property_uses**: Catálogo de usos de propiedades.
9. **property_states**: Catálogo de estados de propiedades.
10. **consignment_types**: Catálogo de tipos de consignación.
11. **excluded_properties**: Almacena las propiedades excluidas de la sincronización.

### Tabla `images`

La tabla `images` tiene la siguiente estructura:

```sql
CREATE TABLE images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    property_id INT NOT NULL,
    url VARCHAR(255) NOT NULL,
    local_url VARCHAR(255) NULL,
    order_num INT DEFAULT 0,
    is_downloaded TINYINT(1) DEFAULT 0,
    is_featured TINYINT(1) DEFAULT 0,
    laravel_disk VARCHAR(50) NULL,
    laravel_path VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (property_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
```

### Tabla `excluded_properties`

La tabla `excluded_properties` tiene la siguiente estructura:

```sql
CREATE TABLE excluded_properties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(50) NOT NULL,
    identifier_type ENUM('id', 'ref', 'sync_code') NOT NULL DEFAULT 'ref',
    reason VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY (identifier, identifier_type),
    INDEX (identifier),
    INDEX (identifier_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
```

## Gestión de Imágenes Destacadas

El sistema permite marcar una imagen como destacada para cada propiedad. Esta funcionalidad se implementa a través del campo `is_featured` en la tabla `images`.

### Comportamiento por Defecto

- Por defecto, la imagen con `order_num = 0` se marca como destacada si no hay otra imagen destacada.
- Si se modifica manualmente el campo `is_featured`, el sistema respeta esta selección durante las sincronizaciones.

### Manejo de Eliminación de Imágenes Destacadas

- Si se elimina una imagen destacada, el sistema asigna automáticamente otra imagen como destacada.
- La prioridad para asignar una nueva imagen destacada es:
  1. Primero la imagen con `order_num = 0`
  2. Luego la imagen con el menor `order_num`
  3. Finalmente, la primera imagen disponible

### Comando para Agregar el Campo `is_featured`

Para bases de datos existentes, se proporciona un comando para agregar el campo `is_featured` a la tabla `images`:

```bash
php commands/add-featured-image.php
```

Este comando:
1. Verifica si el campo `is_featured` ya existe en la tabla `images`
2. Si no existe, lo agrega
3. Configura imágenes destacadas por defecto para propiedades que no tienen ninguna imagen destacada

### Implementación Técnica

La gestión de imágenes destacadas se implementa principalmente en dos clases:

1. **ImageProcessorLaravel**: Se encarga de procesar las imágenes durante la sincronización y asignar el valor `is_featured` a las nuevas imágenes.
2. **ImageSynchronizer**: Gestiona la sincronización de imágenes, incluyendo la detección de cambios y la asignación de una nueva imagen destacada cuando la imagen destacada actual es eliminada.

El método `assignNewFeaturedImage` en la clase `ImageSynchronizer` es el responsable de asignar una nueva imagen destacada cuando la imagen destacada actual es eliminada:

```php
private function assignNewFeaturedImage($propertyId, $propertyRef) {
    // Buscar la primera imagen disponible para la propiedad, preferiblemente con order_num = 0
    $sql = "SELECT id FROM images WHERE property_id = ? ORDER BY order_num = 0 DESC, order_num ASC, id ASC LIMIT 1";
    $stmt = $this->db->prepare($sql);
    $stmt->execute([$propertyId]);
    $newFeaturedImageId = $stmt->fetchColumn();
    
    if ($newFeaturedImageId) {
        // Marcar esta imagen como destacada
        $updateStmt = $this->db->prepare("UPDATE images SET is_featured = 1 WHERE id = ?");
        $updateStmt->execute([$newFeaturedImageId]);
        echo "Propiedad #{$propertyRef}: Nueva imagen destacada asignada (ID: {$newFeaturedImageId})\n";
    } else {
        echo "Propiedad #{$propertyRef}: No se encontraron imágenes para asignar como destacada\n";
    }
}
```

## Sistema de Exclusión de Propiedades

El sistema permite excluir propiedades específicas de la sincronización. Las propiedades excluidas no se sincronizarán y, si ya existen en la base de datos, serán eliminadas durante la próxima sincronización.

### Tipos de Identificadores

El sistema soporta tres tipos de identificadores para excluir propiedades:

1. **id**: ID interno de la propiedad en la base de datos
2. **ref**: Referencia de la propiedad (código externo)
3. **sync_code**: Código de sincronización de la propiedad

### Comando para Gestionar Propiedades Excluidas

Se proporciona un comando para gestionar las propiedades excluidas:

```bash
php commands/manage-excluded-properties.php [opciones]
```

Opciones disponibles:
- `--add=VALOR`: Agregar una propiedad a la lista de exclusión
- `--remove=VALOR`: Eliminar una propiedad de la lista de exclusión
- `--list`: Listar todas las propiedades excluidas
- `--reason=TEXTO`: Especificar una razón para la exclusión (usar con --add)
- `--type=TIPO`: Especificar el tipo de identificador (id, ref, sync_code)

Ejemplos:
```bash
# Agregar una propiedad por referencia
php commands/manage-excluded-properties.php --add=123 --reason="Propiedad duplicada"

# Agregar una propiedad por ID
php commands/manage-excluded-properties.php --add=456 --type=id --reason="Propiedad incorrecta"

# Agregar una propiedad por código de sincronización
php commands/manage-excluded-properties.php --add=789 --type=sync_code --reason="Propiedad obsoleta"

# Eliminar una propiedad de la lista de exclusión
php commands/manage-excluded-properties.php --remove=123

# Listar todas las propiedades excluidas
php commands/manage-excluded-properties.php --list
```

### Comportamiento durante la Sincronización

Durante la sincronización, el sistema realiza las siguientes acciones con respecto a las propiedades excluidas:

1. **Verificación de Propiedades Existentes**: Verifica si alguna propiedad excluida ya existe en la base de datos y, en caso afirmativo, la elimina junto con sus imágenes y características asociadas.

2. **Filtrado de Propiedades de la API**: Verifica cada propiedad recibida de la API y omite aquellas que están en la lista de exclusión.

### Implementación Técnica

El sistema de exclusión de propiedades se implementa principalmente en dos archivos:

1. **manage-excluded-properties.php**: Comando para gestionar las propiedades excluidas.
2. **sync-complete.php**: Script principal de sincronización que verifica y procesa las propiedades excluidas.

El proceso de verificación de propiedades excluidas durante la sincronización se realiza de la siguiente manera:

```php
// Verificar si la propiedad está excluida por alguno de sus identificadores
$isExcluded = false;
$exclusionReason = '';

// Verificar por ID
if ($id && isset($excludedProperties['id'][$id])) {
    $isExcluded = true;
    $exclusionReason = $excludedProperties['id'][$id] ?: 'No especificada';
}
// Verificar por referencia
elseif ($ref && isset($excludedProperties['ref'][$ref])) {
    $isExcluded = true;
    $exclusionReason = $excludedProperties['ref'][$ref] ?: 'No especificada';
}
// Verificar por código de sincronización
elseif ($syncCode && isset($excludedProperties['sync_code'][$syncCode])) {
    $isExcluded = true;
    $exclusionReason = $excludedProperties['sync_code'][$syncCode] ?: 'No especificada';
}

if ($isExcluded) {
    echo "\nInmueble #{$count}/{$propertiesToProcess} ({$percentage}%): Ref {$ref} - EXCLUIDO DE LA SINCRONIZACIÓN\n";
    echo "Razón: {$exclusionReason}\n";
    continue; // Saltar esta propiedad
}
```

## Comandos Disponibles

El sistema proporciona varios comandos para realizar diferentes operaciones:

### Sincronización de Datos

```bash
# Sincronización completa
php commands/sync-complete.php

# Sincronización de una propiedad específica
php commands/sync-complete.php --property=REF

# Sincronización limitada a un número específico de propiedades
php commands/sync-complete.php --limit=N

# Crear tablas sin sincronizar datos
php commands/sync-complete.php --no-sync
```

### Gestión de Imágenes Destacadas

```bash
# Agregar el campo is_featured a la tabla images y configurar imágenes destacadas por defecto
php commands/add-featured-image.php
```

### Gestión de Propiedades Excluidas

```bash
# Agregar una propiedad a la lista de exclusión
php commands/manage-excluded-properties.php --add=VALOR --type=TIPO --reason=TEXTO

# Eliminar una propiedad de la lista de exclusión
php commands/manage-excluded-properties.php --remove=VALOR --type=TIPO

# Listar todas las propiedades excluidas
php commands/manage-excluded-properties.php --list
```

### Limpieza de Datos

```bash
# Borrar todos los datos de las tablas pero mantener la estructura
php commands/clear-data.php

# Borrar todos los datos de las tablas y eliminar también las imágenes físicas
php commands/clear-data.php --with-images

# Eliminar completamente todas las tablas
php commands/drop-tables.php

# Eliminar completamente todas las tablas y también las imágenes físicas
php commands/drop-tables.php --with-images
```

## Flujo de Sincronización

El proceso de sincronización sigue los siguientes pasos:

1. **Verificación de Tablas**: Verifica si todas las tablas necesarias existen y las crea si no existen.

2. **Verificación de Carpeta de Imágenes**: Verifica si la carpeta de imágenes existe y la crea si no existe.

3. **Obtención de Datos de la API**: Obtiene los datos de las propiedades desde la API externa.

4. **Procesamiento de Propiedades Excluidas**: Verifica si hay propiedades excluidas en la base de datos y las elimina.

5. **Procesamiento de Propiedades**: Para cada propiedad recibida de la API:
   - Verifica si está excluida y la omite si es así
   - Verifica si ya existe en la base de datos
   - Si existe, actualiza sus datos si han cambiado
   - Si no existe, la crea
   - Procesa sus características
   - Procesa sus imágenes

6. **Generación de Resumen**: Genera un resumen de la sincronización con estadísticas.

### Procesamiento de Imágenes

El procesamiento de imágenes sigue los siguientes pasos:

1. **Obtención de Imágenes Existentes**: Obtiene las imágenes existentes para la propiedad desde la base de datos.

2. **Detección de Cambios**: Detecta si alguna imagen ha cambiado (misma URL pero contenido diferente).

3. **Descarga de Nuevas Imágenes**: Descarga las nuevas imágenes desde la API.

4. **Eliminación de Imágenes Obsoletas**: Elimina las imágenes que ya no existen en la API.

5. **Asignación de Imagen Destacada**: Asegura que la propiedad tenga una imagen destacada.

## Solución de Problemas

### Problemas Comunes

1. **Error de Conexión a la Base de Datos**:
   - Verificar que las credenciales de la base de datos en el archivo `.env` sean correctas
   - Verificar que el servidor de base de datos esté en ejecución
   - Ejecutar `php commands/debug-env.php` para diagnosticar problemas con las variables de entorno

2. **Error al Descargar Imágenes**:
   - Verificar que la carpeta de imágenes tenga permisos de escritura
   - Verificar que la URL de la imagen sea accesible
   - Verificar que haya suficiente espacio en disco

3. **Error al Sincronizar Propiedades**:
   - Verificar que la API esté disponible
   - Verificar que el formato de la respuesta de la API no haya cambiado
   - Ejecutar la sincronización con una propiedad específica para depurar el problema

### Comandos de Depuración

```bash
# Depurar variables de entorno y conexión a la base de datos
php commands/debug-env.php

# Sincronizar una propiedad específica para depurar
php commands/sync-complete.php --property=REF
```

### Registros de Errores

El sistema genera mensajes de error detallados en la consola durante la sincronización. Estos mensajes incluyen:

- Errores de conexión a la base de datos
- Errores al descargar imágenes
- Errores al procesar propiedades
- Errores al eliminar propiedades excluidas

Para capturar estos mensajes en un archivo de registro, puede redirigir la salida del comando:

```bash
php commands/sync-complete.php > sync_log.txt 2>&1
```

## Conclusión

El sistema SyncOrbisPhp proporciona una solución completa para sincronizar datos de propiedades inmobiliarias desde una API externa a una base de datos local. Con funcionalidades como la gestión de imágenes destacadas y la exclusión de propiedades, el sistema ofrece flexibilidad y control sobre el proceso de sincronización.

Para cualquier problema o consulta adicional, consulte la documentación de código fuente o contacte al equipo de desarrollo.
