# SyncOrbisPhp

Sistema de sincronización de propiedades inmobiliarias para MySQL, desarrollado en PHP 8+, con soporte para convenciones de Laravel.

## Descripción

SyncOrbisPhp es un sistema diseñado para sincronizar datos de propiedades inmobiliarias desde una API externa a una base de datos MySQL local. Esta versión está optimizada para PHP 8 o superior y sigue las convenciones de Laravel para nombres de tablas y columnas. El sistema permite la sincronización completa de propiedades, incluyendo sus características e imágenes, y proporciona comandos para gestionar la limpieza y reseteo de datos.

## Características

- Sincronización completa de propiedades inmobiliarias desde una API externa
- Descarga y gestión automática de imágenes con soporte para Laravel
- Almacenamiento de referencias (campo `ref`) de propiedades desde la API
- Identificación de valores numéricos en características (campo `is_numeric`)
- Eliminación física de imágenes y carpetas vacías al resetear la base de datos
- Convenciones de Laravel para nombres de tablas y columnas
- Configuración flexible mediante variables de entorno
- Comandos específicos para diferentes operaciones de limpieza y sincronización

## Requisitos

- PHP 7.4 o superior
- Extensión PDO para MySQL
- Extensión cURL para PHP
- MySQL 5.7 o superior
- Acceso a la API externa (URL configurada en el archivo .env)

## Base de Datos con Docker

Para facilitar el desarrollo, se incluye una configuración de Docker para MySQL:

1. Instale Docker y Docker Compose si aún no los tiene instalados.

2. Inicie la base de datos MySQL y phpMyAdmin con Docker Compose:
   ```bash
   docker-compose up -d
   ```

3. Acceda a phpMyAdmin en http://localhost:8080 con estas credenciales:
   - Servidor: mysql
   - Usuario: syncorbis
   - Contraseña: syncorbis123
   - Base de datos: OrbisAHOPHP

4. Para detener los contenedores:
   ```bash
   docker-compose down
   ```

## Instalación

1. Clone el repositorio:
   ```
   git clone https://github.com/usuario/SyncOrbisPhp.git
   cd SyncOrbisPhp
   ```

2. Copie el archivo de configuración de ejemplo:
   ```
   cp config/.env.example config/.env
   ```

3. Edite el archivo `config/.env` con sus credenciales de base de datos y API:
   ```
   DB_HOST=localhost
   DB_PORT=3306
   DB_DATABASE=orbis
   DB_USERNAME=usuario
   DB_PASSWORD=contraseña
   
   API_URL=https://api.orbis.com/properties
   API_KEY=su_clave_api
   
   IMAGES_FOLDER=public/images
   SYNC_LIMIT=0
   DOWNLOAD_IMAGES=true
   TRACK_CHANGES=true
   ```

4. Asegúrese de que las carpetas necesarias tengan permisos de escritura:
   ```
   chmod -R 755 public/images
   chmod -R 755 logs
   ```

## Uso

### Sincronización Completa

Para sincronizar todas las propiedades:

```bash
php commands/sync-complete.php
```

### Opciones de Sincronización

```bash
php commands/sync-complete.php [opciones]
```

Opciones disponibles:
- `--limit=N`: Limitar a N propiedades
- `--property=REF`: Sincronizar solo la propiedad con referencia REF
- `--reset`: Eliminar todas las tablas antes de sincronizar
- `--no-sync`: Solo crear las tablas sin sincronizar datos
- `--help`: Mostrar ayuda

Ejemplos:

```bash
# Sincronizar solo 10 propiedades
php commands/sync-complete.php --limit=10

# Sincronizar solo la propiedad con referencia 250
php commands/sync-complete.php --property=250

# Resetear la base de datos y sincronizar 5 propiedades
php commands/sync-complete.php --reset --limit=5

# Solo crear las tablas sin sincronizar datos
php commands/sync-complete.php --no-sync
```

### Limpiar Datos

Para borrar todos los datos de las tablas pero mantener la estructura (TRUNCATE):

```bash
php commands/clear-data.php [--with-images]
```

Si se especifica la opción `--with-images`, también elimina las imágenes físicas y las carpetas vacías.

### Eliminar Tablas

Para eliminar completamente todas las tablas (DROP TABLE):

```bash
php commands/drop-tables.php [--with-images]
```

Si se especifica la opción `--with-images`, también elimina las imágenes físicas y las carpetas vacías.

### Verificar Rutas de Imágenes

Para verificar cómo se están guardando las rutas de las imágenes en la base de datos:

```bash
php commands/check-image-paths.php
```

## Estructura del proyecto

```
SyncOrbisPhp/
├── commands/           # Comandos de consola
│   ├── sync-complete.php   # Sincronización completa de propiedades
│   ├── clear-data.php      # Borrar datos manteniendo estructura
│   ├── drop-tables.php     # Eliminar tablas completamente
│   ├── check-image-paths.php # Verificar rutas de imágenes
│   ├── optimize.php    # Optimizar rendimiento
│   └── reset.php       # Reiniciar base de datos
├── config/             # Archivos de configuración
│   ├── .env.example    # Plantilla de variables de entorno
│   └── config.php      # Configuración principal
├── docker/             # Configuración de Docker
│   └── mysql/init/     # Scripts de inicialización para MySQL
├── docs/               # Documentación
│   ├── despliegue.md    # Guía de despliegue
│   └── recomendaciones_rendimiento.md # Recomendaciones de rendimiento
├── logs/               # Archivos de registro
├── public/             # Archivos públicos
│   └── images/         # Imágenes descargadas
├── src/                # Código fuente
│   ├── BatchProcessor.php # Procesamiento por lotes
│   ├── CacheManager.php # Gestión de caché
│   ├── Database.php    # Gestión de base de datos
│   ├── ImageProcessor.php # Procesador de imágenes
│   ├── Logger.php      # Sistema de registro
│   ├── Optimizer.php    # Optimizador de rendimiento
│   ├── PropertyProcessor.php # Procesador de propiedades
│   └── Synchronizer.php # Sincronizador principal
├── docker-compose.yml  # Configuración de Docker Compose
├── sync.php            # Script principal
└── sync_optimized.php  # Script optimizado
```

## Integración con Laravel

### Configuración para Laravel

El sistema está diseñado para ser compatible con Laravel. Para configurarlo correctamente:

1. Configura las variables de entorno en el archivo `.env`:

```
IMAGES_STORAGE_MODE=laravel
LARAVEL_STORAGE_PATH=/ruta/a/tu/proyecto/laravel/storage/app/public
LARAVEL_IMAGES_PATH=images/inmuebles
```

2. Asegúrate de haber ejecutado el comando `php artisan storage:link` en tu proyecto Laravel para crear el enlace simbólico a la carpeta de almacenamiento.

### Convenciones de Laravel

El sistema utiliza las convenciones de Laravel para nombres de tablas y columnas:

- Tablas en inglés y plural (ej: properties, images, advisors)
- Claves foráneas con el formato tabla_singular_id (ej: property_id, city_id)
- Columnas booleanas con el prefijo is_ (ej: is_active, is_featured)

### Visualización de Imágenes en Laravel

Para mostrar las imágenes en tu aplicación Laravel, puedes usar los campos `laravel_disk` y `laravel_path` de la tabla `images`:

```php
// En una vista Blade
<img src="{{ asset('storage/' . $image->laravel_path) }}" alt="Imagen de propiedad">

// O usando el Storage Facade
<img src="{{ Storage::disk($image->laravel_disk)->url($image->laravel_path) }}" alt="Imagen de propiedad">
```

2. Asegúrese de que la base de datos configurada sea la misma que utiliza su aplicación Laravel.

3. Puede ejecutar el script de sincronización como un comando programado en Laravel, añadiendo la siguiente línea a su `app/Console/Kernel.php`:
   ```php
   protected function schedule(Schedule $schedule)
   {
       $schedule->exec('php /ruta/a/SyncOrbisPhp/commands/sync-complete.php')->daily();
   }
   ```

## Campos Especiales

### Campo `ref` en la tabla `properties`

El campo `ref` almacena la referencia de la propiedad desde la API. Este campo se utiliza para identificar propiedades en la sincronización y es útil para buscar propiedades específicas.

### Campo `is_numeric` en la tabla `property_characteristics`

El campo `is_numeric` indica si el valor de la característica es numérico (1) o una cadena de texto (0). Esto es útil para realizar consultas y filtros basados en valores numéricos.

## Mantenimiento del Sistema

### Limpieza de Imágenes

Cuando se resetea la base de datos o se eliminan propiedades, el sistema puede eliminar las imágenes físicas asociadas utilizando las opciones `--with-images` en los comandos `clear-data.php` y `drop-tables.php`. Esto garantiza que no queden imágenes huérfanas en el sistema de archivos.

### Eliminación de Carpetas Vacías

El sistema también elimina las carpetas vacías después de eliminar las imágenes, manteniendo limpio el sistema de archivos. Esto se hace automáticamente cuando se utiliza la opción `--with-images`.

### Verificación de Rutas de Imágenes

Para verificar que las rutas de las imágenes se están guardando correctamente y que son compatibles con Laravel, puedes usar el comando `check-image-paths.php`:

```bash
php commands/check-image-paths.php
```

Este comando muestra ejemplos de cómo se están guardando las rutas en la base de datos y cómo acceder a ellas desde Laravel.

## Estructura de la Base de Datos

El sistema crea y mantiene las siguientes tablas principales:

- `properties`: Almacena la información básica de las propiedades inmobiliarias.
- `property_characteristics`: Almacena las características de las propiedades (metros cuadrados, habitaciones, etc.).
- `images`: Almacena las imágenes asociadas a las propiedades, incluyendo rutas para Laravel.
- `advisors`: Almacena la información de los asesores inmobiliarios.
- `cities`: Almacena las ciudades donde se encuentran las propiedades.
- `zones`: Almacena las zonas o barrios dentro de las ciudades.

### Campos Importantes en la Tabla `images`

- `laravel_disk`: Disco de almacenamiento de Laravel (generalmente 'public').
- `laravel_path`: Ruta relativa de la imagen para ser usada con Laravel.
- `local_url`: Ruta completa al archivo local de la imagen.
- `url`: URL original de la imagen desde la API.

## Optimización de Rendimiento

```bash
php commands/optimize.php
```

Este comando aplicará varias optimizaciones para mejorar la velocidad de sincronización:

- Optimizará la estructura de la base de datos
- Creará índices para mejorar las consultas
- Configurará el caché para datos frecuentemente utilizados
- Ajustará la configuración de PHP para mejor rendimiento

Adicionalmente, puede utilizar el script de sincronización optimizado para un mejor rendimiento:

```bash
php sync_optimized.php --batch-size=50 --use-cache
```

Para más información, consulte [Recomendaciones de Rendimiento](docs/recomendaciones_rendimiento.md).

## Despliegue

SyncOrbisPhp puede desplegarse en diferentes entornos, desde desarrollo local hasta producción. Para más información sobre opciones de despliegue, consulte la [Guía de Despliegue](docs/despliegue.md).

Las opciones de despliegue incluyen:
- Hosting compartido (como Namecheap)
- Plataformas gratuitas (Railway, PlanetScale, Clever Cloud)
- AWS con capa gratuita
- Integración con Laravel

## Licencia

Este proyecto está licenciado bajo la Licencia MIT - vea el archivo LICENSE para más detalles.
