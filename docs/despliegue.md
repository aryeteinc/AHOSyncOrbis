# Guía de Despliegue para SyncOrbisPhp

Esta guía proporciona instrucciones detalladas para desplegar SyncOrbisPhp en diferentes entornos, desde desarrollo local hasta producción.

## Opciones de Despliegue

### 1. Despliegue Local (Desarrollo)

Para un entorno de desarrollo local, simplemente siga estos pasos:

1. Clone el repositorio:
   ```bash
   git clone https://github.com/usuario/SyncOrbisPhp.git
   cd SyncOrbisPhp
   ```

2. Configure el archivo `.env`:
   ```bash
   cp config/.env.example config/.env
   # Edite config/.env con sus credenciales
   ```

3. Asegúrese de que las carpetas tengan permisos adecuados:
   ```bash
   chmod -R 755 public/images
   chmod -R 755 logs
   ```

4. Ejecute el script de sincronización:
   ```bash
   php sync.php
   ```

### 2. Despliegue en Hosting Compartido (Producción)

Para desplegar en un hosting compartido como Namecheap, siga estos pasos:

1. Suba todos los archivos a su hosting mediante FTP o el administrador de archivos de cPanel.

2. Configure el archivo `.env` con sus credenciales de base de datos y API.

3. Asegúrese de que PHP 8.0 o superior esté configurado como versión predeterminada.

4. Configure un trabajo Cron para ejecutar la sincronización automáticamente:
   ```
   # Ejecutar la sincronización cada día a las 2 AM
   0 2 * * * php /ruta/completa/a/SyncOrbisPhp/sync.php >> /ruta/completa/a/SyncOrbisPhp/logs/cron.log 2>&1
   ```

### 3. Despliegue en Plataformas Gratuitas

#### Railway.app (Recomendado)

Railway ofrece una capa gratuita que incluye MySQL nativo:

1. Regístrese en [Railway](https://railway.app/)
2. Cree un nuevo proyecto
3. Añada un servicio MySQL
4. Añada un servicio PHP (seleccione PHP 8.1 o superior)
5. Configure las variables de entorno según su archivo `.env`
6. Conecte su repositorio de GitHub
7. Configure un trabajo programado para la sincronización

#### PlanetScale (MySQL como servicio)

PlanetScale ofrece un plan gratuito con 5GB de almacenamiento MySQL:

1. Regístrese en [PlanetScale](https://planetscale.com/)
2. Cree una nueva base de datos
3. Obtenga las credenciales de conexión
4. Configure su aplicación PHP en otro servicio como Vercel o Netlify
5. Configure las variables de entorno con las credenciales de PlanetScale

#### Clever Cloud

Clever Cloud ofrece un plan gratuito con MySQL:

1. Regístrese en [Clever Cloud](https://www.clever-cloud.com/)
2. Cree una nueva aplicación PHP
3. Añada un complemento MySQL
4. Configure las variables de entorno
5. Conecte su repositorio Git
6. Configure un trabajo cron para la sincronización

#### AWS con Capa Gratuita

AWS ofrece una capa gratuita por 12 meses que incluye:

1. EC2 para alojar la aplicación PHP
2. RDS para la base de datos MySQL
3. Configure un grupo de seguridad para permitir la comunicación entre EC2 y RDS
4. Utilice un trabajo cron en la instancia EC2 para la sincronización automática

#### Base de datos MySQL local + túnel Ngrok

Si desea mantener su base de datos local pero acceder a ella desde Internet:

1. Configure MySQL en su máquina local
2. Use Ngrok para crear un túnel seguro:
   ```bash
   ngrok tcp 3306
   ```
3. Configure su aplicación para conectarse a la URL proporcionada por Ngrok

## Integración con Laravel

Para integrar SyncOrbisPhp con Laravel:

1. Coloque el código de SyncOrbisPhp en una carpeta dentro de su proyecto Laravel (por ejemplo, `syncorbis/`).

2. Configure el archivo `.env` para que apunte a la misma base de datos que usa Laravel:
   ```
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=laravel
   DB_USERNAME=root
   DB_PASSWORD=
   
   # Configurar la ruta de imágenes para usar la carpeta de storage de Laravel
   IMAGES_FOLDER=public/storage/images/inmuebles
   LARAVEL_PUBLIC_PATH=/ruta/completa/a/su/proyecto/laravel/public
   ```

3. Cree un comando de artisan para ejecutar la sincronización:
   ```php
   // app/Console/Commands/SyncOrbisCommand.php
   namespace App\Console\Commands;
   
   use Illuminate\Console\Command;
   
   class SyncOrbisCommand extends Command
   {
       protected $signature = 'syncorbis:sync {--limit=0} {--force} {--no-images}';
       protected $description = 'Sincronizar datos desde la API de Orbis';
   
       public function handle()
       {
           $limit = $this->option('limit');
           $force = $this->option('force');
           $noImages = $this->option('no-images');
           
           $command = "php " . base_path('syncorbis/sync.php');
           
           if ($limit > 0) {
               $command .= " --limit={$limit}";
           }
           
           if ($force) {
               $command .= " --force";
           }
           
           if ($noImages) {
               $command .= " --no-images";
           }
           
           $this->info("Ejecutando sincronización...");
           exec($command, $output, $returnVar);
           
           foreach ($output as $line) {
               $this->line($line);
           }
           
           return $returnVar === 0 ? 0 : 1;
       }
   }
   ```

4. Registre el comando en `app/Console/Kernel.php`:
   ```php
   protected function schedule(Schedule $schedule)
   {
       $schedule->command('syncorbis:sync')->daily();
   }
   ```

## Solución de Problemas Comunes

### Error de Conexión a la Base de Datos

Si recibe errores de conexión a la base de datos:

1. Verifique que las credenciales en el archivo `.env` sean correctas
2. Asegúrese de que la base de datos exista y esté accesible
3. Verifique que el usuario tenga permisos adecuados

### Error al Descargar Imágenes

Si hay problemas con la descarga de imágenes:

1. Verifique que la carpeta de imágenes tenga permisos de escritura
2. Asegúrese de que PHP tenga habilitada la extensión `fileinfo`
3. Verifique que PHP pueda realizar solicitudes HTTP externas

### Problemas de Memoria

Si la sincronización falla por problemas de memoria:

1. Aumente el límite de memoria en `php.ini`: `memory_limit = 256M`
2. Use la opción `--limit` para sincronizar en lotes más pequeños
3. Desactive la descarga de imágenes con `--no-images` si solo necesita los datos
