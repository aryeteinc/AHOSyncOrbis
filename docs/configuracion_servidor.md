# Guía Completa de Despliegue en Servidor Compartido (Namecheap)

Esta guía detallada explica paso a paso cómo configurar SyncOrbisPhp en un servidor compartido de Namecheap, asegurando que solo sea accesible a través de comandos de consola y no mediante navegador web.

## Paso 1: Preparación Local del Proyecto

### 1.1 Ejecutar el Script de Preparación

Antes de subir el proyecto al servidor, ejecuta el script de preparación en tu entorno local:

```bash
cd /ruta/a/SyncOrbisPhp
php prepare_for_production.php
```

Este script realizará las siguientes acciones:
- Verificará la estructura de directorios
- Creará un archivo `.htaccess` para proteger el acceso web
- Establecerá los permisos correctos para archivos y directorios
- Verificará la configuración del archivo `.env`

### 1.2 Comprimir el Proyecto

Comprimir el proyecto facilitará su transferencia al servidor:

```bash
# Desde el directorio padre del proyecto
zip -r syncorbis.zip SyncOrbisPhp
```

## Paso 2: Acceso al Panel de Control de Namecheap

### 2.1 Iniciar Sesión en cPanel

1. Accede a tu cuenta de Namecheap
2. Ve a "Administrar" junto al dominio que estás utilizando
3. Haz clic en "cPanel" o "Administrar"
4. Inicia sesión con tus credenciales de cPanel

## Paso 3: Configuración de la Base de Datos

### 3.1 Crear Base de Datos MySQL

1. En cPanel, busca la sección "Bases de datos" y haz clic en "MySQL Databases"
2. En "Create New Database", introduce un nombre para tu base de datos y haz clic en "Create Database"
   - El nombre completo de la base de datos será `username_nombrebd`

### 3.2 Crear Usuario de Base de Datos

1. En la misma página, desplázate hacia abajo hasta "MySQL Users"
2. Crea un nuevo usuario con una contraseña segura
   - El nombre completo del usuario será `username_nombreusuario`

### 3.3 Asignar Usuario a la Base de Datos

1. Desplázate hacia abajo hasta "Add User To Database"
2. Selecciona el usuario y la base de datos que acabas de crear
3. Haz clic en "Add"
4. En la página de privilegios, selecciona "ALL PRIVILEGES" y haz clic en "Make Changes"

## Paso 4: Subir Archivos al Servidor

### 4.1 Crear Directorio para la Aplicación

Para mayor seguridad, es recomendable colocar la aplicación fuera del directorio público web:

1. En cPanel, busca "File Manager"
2. Navega hasta el directorio raíz (generalmente `/home/username/`)
3. Crea una nueva carpeta llamada `syncorbis` (o el nombre que prefieras)

### 4.2 Subir y Descomprimir el Archivo ZIP

1. En File Manager, navega hasta la carpeta que acabas de crear
2. Haz clic en "Upload" en la barra superior
3. Sube el archivo `syncorbis.zip`
4. Una vez subido, selecciona el archivo y haz clic en "Extract"
5. Extrae el contenido en el directorio actual

### 4.3 Configurar Permisos

Establece los permisos correctos para los directorios y archivos:

1. Selecciona la carpeta `public/images` y haz clic en "Permissions"
2. Establece los permisos a 755 (o 777 si es necesario)
3. Marca la casilla "Recurse into subdirectories"
4. Haz clic en "Change Permissions"
5. Repite el proceso para la carpeta `logs`

### 4.4 Configurar Archivo .env

1. Navega hasta la carpeta `config`
2. Edita el archivo `.env` (o crea uno nuevo basado en `.env.example`)
3. Actualiza la configuración con los datos de tu base de datos:

```
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=username_nombrebd
DB_USERNAME=username_nombreusuario
DB_PASSWORD=tu_contraseña

API_URL=https://api.example.com/properties
API_KEY=tu_clave_api

IMAGES_STORAGE_MODE=laravel
LARAVEL_STORAGE_PATH=/home/username/public_html/storage/app/public
LARAVEL_IMAGES_PATH=images/inmuebles
```

## Paso 5: Verificar Acceso SSH

### 5.1 Habilitar Acceso SSH

1. En cPanel, busca "SSH Access" o "SSH/Shell Access"
2. Si el acceso SSH no está habilitado, contacta con el soporte de Namecheap para habilitarlo

### 5.2 Generar o Subir Claves SSH (Opcional)

1. En la sección "SSH Access", puedes generar un nuevo par de claves SSH o subir tus claves públicas existentes
2. Sigue las instrucciones en pantalla para configurar la autenticación por clave

### 5.3 Conectarse por SSH

1. Abre una terminal en tu computadora local
2. Conéctate al servidor usando el siguiente comando:
   ```bash
   ssh username@tu-dominio.com
   ```
3. Introduce tu contraseña cuando se te solicite

### 5.4 Navegar al Directorio del Proyecto

```bash
cd /home/username/syncorbis
```

### 5.5 Probar la Aplicación

Ejecuta un comando de prueba para verificar que todo funciona correctamente:

```bash
php commands/test-connection.php
```

## Paso 6: Configurar Tareas Programadas (Cron Jobs)

### 6.1 Acceder al Administrador de Cron Jobs

1. En cPanel, busca y haz clic en "Cron Jobs"

### 6.2 Configurar un Nuevo Cron Job

1. Desplázate hacia abajo hasta "Add New Cron Job"

2. Configura la frecuencia de ejecución:
   - **Minuto**: 0
   - **Hora**: 3
   - **Día**: *
   - **Mes**: *
   - **Día de la semana**: *
   
   Esto ejecutará el script todos los días a las 3:00 AM.

3. En el campo "Command", introduce el siguiente comando:

   ```
   /usr/local/bin/php /home/username/syncorbis/commands/sync-complete.php >> /home/username/syncorbis/logs/cron.log 2>&1
   ```

   Este comando:
   - Ejecuta el script de sincronización completa
   - Redirige la salida estándar y de error a un archivo de registro

4. Haz clic en "Add New Cron Job"

### 6.3 Cron Jobs Adicionales (Opcional)

Puedes configurar cron jobs adicionales para otras tareas:

1. **Limpieza de logs antiguos** (cada semana):
   ```
   0 4 * * 0 find /home/username/syncorbis/logs -name "*.log" -type f -mtime +30 -delete
   ```

2. **Verificación de imágenes** (cada mes):
   ```
   0 5 1 * * /usr/local/bin/php /home/username/syncorbis/commands/check-images.php >> /home/username/syncorbis/logs/check-images.log 2>&1
   ```

## Paso 7: Verificar la Instalación

### 7.1 Ejecutar Sincronización Inicial

Conecta por SSH y ejecuta:

```bash
cd /home/username/syncorbis
php commands/sync-complete.php --limit=5
```

Esto sincronizará 5 propiedades para verificar que todo funciona correctamente.

### 7.2 Verificar Logs

Revisa los logs generados para asegurarte de que no hay errores:

```bash
cat logs/sync.log
```

### 7.3 Verificar Base de Datos

1. En cPanel, ve a "phpMyAdmin"
2. Selecciona tu base de datos
3. Verifica que las tablas se hayan creado correctamente
4. Comprueba que hay datos en las tablas principales como `properties`

## Paso 8: Protección Adicional (Opcional)

### 8.1 Restringir IP para Acceso SSH

1. En cPanel, busca "SSH Access" o "Security"
2. Configura las IP permitidas para acceso SSH

### 8.2 Configurar Alertas por Email

Añade un cron job para enviar alertas por email:

```
0 6 * * * /usr/local/bin/php /home/username/syncorbis/commands/check-sync-status.php | mail -s "Estado de sincronización" tu@email.com
```

## Solución de Problemas Comunes

### Error de Permisos

Si encuentras errores de permisos al ejecutar los scripts:

```bash
chmod -R 755 /home/username/syncorbis
chmod -R 777 /home/username/syncorbis/public/images
chmod -R 777 /home/username/syncorbis/logs
```

### Error de Versión de PHP

1. En cPanel, busca "PHP Selector" o "MultiPHP Manager"
2. Selecciona la versión de PHP 7.4 o superior para tu dominio
3. Guarda los cambios

### Error de Extensiones PHP

1. En cPanel, ve a "MultiPHP INI Editor" o "PHP Selector"
2. Selecciona tu dominio
3. Asegúrate de que las siguientes extensiones estén habilitadas:
   - pdo
   - pdo_mysql
   - curl
   - json
   - fileinfo

### Error de Conexión a la Base de Datos

1. Verifica que los datos en el archivo `.env` sean correctos
2. Asegúrate de que el usuario tenga los permisos adecuados
3. Comprueba que la base de datos exista

### Error de Espacio en Disco

1. En cPanel, verifica el uso de espacio en disco
2. Si estás cerca del límite, considera eliminar imágenes antiguas:
   ```bash
   php commands/clear-images.php --older-than=90
   ```

## Mantenimiento Rutinario

### Respaldo de la Base de Datos

1. En cPanel, ve a "Backups" o "Backup Wizard"
2. Genera un respaldo de tu base de datos regularmente

### Actualización del Sistema

Para actualizar el sistema a una nueva versión:

1. Haz una copia de seguridad de la base de datos
2. Sube los nuevos archivos al servidor
3. Ejecuta cualquier script de migración necesario
