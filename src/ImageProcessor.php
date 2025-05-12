<?php
/**
 * Clase para procesar imágenes de propiedades inmobiliarias
 */
class ImageProcessor {
    private $db;
    private $imagesFolder;
    private $stats;
    private $storageMode;
    private $laravelDisk;
    private $laravelImagesPath;
    
    /**
     * Constructor
     * 
     * @param PDO $db Instancia de la base de datos
     * @param string $imagesFolder Carpeta para las imágenes
     * @param array &$stats Referencia a las estadísticas de sincronización
     * @param string $storageMode Modo de almacenamiento ('local' o 'laravel')
     * @param string $laravelDisk Nombre del disco de Laravel (generalmente 'public')
     * @param string $laravelImagesPath Ruta relativa dentro del disco de Laravel
     */
    public function __construct($db, $imagesFolder, &$stats, $storageMode = 'local', $laravelDisk = 'public', $laravelImagesPath = 'images/inmuebles') {
        $this->db = $db;
        $this->imagesFolder = $imagesFolder;
        $this->stats = &$stats;
        $this->storageMode = $storageMode;
        $this->laravelDisk = $laravelDisk;
        $this->laravelImagesPath = $laravelImagesPath;
    }
    
    /**
     * Procesar imágenes de un inmueble
     * 
     * @param int $propertyId ID del inmueble
     * @param int $propertyRef Referencia del inmueble
     * @param array $images Imágenes a procesar
     * @return array Imágenes procesadas
     */
    public function processImages($propertyId, $propertyRef, $images) {
        if (empty($images) || !is_array($images)) {
            echo "Inmueble #{$propertyRef}: No hay imágenes para procesar\n";
            return [];
        }
        
        echo "Inmueble #{$propertyRef}: Procesando " . count($images) . " imágenes...\n";
        
        // Determinar la ruta de almacenamiento según el modo configurado
        $storageBasePath = '';
        $laravelRelativePath = '';
        
        if ($this->storageMode === 'laravel') {
            // En modo Laravel, guardar en la estructura de directorios de Laravel
            $storageBasePath = rtrim($this->imagesFolder, '/');
            $laravelRelativePath = rtrim($this->laravelImagesPath, '/') . "/inmueble_{$propertyRef}";
            $propertyFolder = $storageBasePath . '/' . $laravelRelativePath;
            
            echo "Inmueble #{$propertyRef}: Usando modo de almacenamiento Laravel (Disco: {$this->laravelDisk}, Ruta: {$laravelRelativePath})\n";
        } else {
            // En modo local, usar la estructura de directorios tradicional
            $propertyFolder = rtrim($this->imagesFolder, '/') . "/inmueble_{$propertyRef}";
            echo "Inmueble #{$propertyRef}: Usando modo de almacenamiento local\n";
        }
        
        // Asegurarse de que la carpeta principal de imágenes existe
        if (!is_dir($this->imagesFolder)) {
            echo "Creando carpeta principal de imágenes: {$this->imagesFolder}\n";
            if (!mkdir($this->imagesFolder, 0755, true)) {
                echo "Error al crear la carpeta principal de imágenes\n";
                // Intentar con el comando mkdir
                $command = "mkdir -p " . escapeshellarg($this->imagesFolder);
                system($command, $returnCode);
                if ($returnCode !== 0) {
                    echo "Error al crear la carpeta principal con el comando mkdir\n";
                    throw new \Exception("No se pudo crear la carpeta principal de imágenes: {$this->imagesFolder}");
                }
            }
        }
        
        // Crear la carpeta específica para el inmueble si no existe
        if (!is_dir($propertyFolder)) {
            echo "Inmueble #{$propertyRef}: Creando carpeta para imágenes: {$propertyFolder}\n";
            if (!mkdir($propertyFolder, 0755, true)) {
                echo "Inmueble #{$propertyRef}: Error al crear la carpeta para imágenes\n";
                // Intentar con el comando mkdir
                $command = "mkdir -p " . escapeshellarg($propertyFolder);
                system($command, $returnCode);
                if ($returnCode !== 0) {
                    echo "Inmueble #{$propertyRef}: Error al crear la carpeta con el comando mkdir\n";
                    // Si no se puede crear la carpeta, usar el directorio principal
                    $propertyFolder = $this->imagesFolder;
                    $laravelRelativePath = rtrim($this->laravelImagesPath, '/');
                    echo "Inmueble #{$propertyRef}: Usando directorio principal: {$propertyFolder}\n";
                }
            }
        } else {
            echo "Inmueble #{$propertyRef}: Usando carpeta existente para imágenes: {$propertyFolder}\n";
        }
        
        // Verificar permisos de la carpeta
        if (is_dir($propertyFolder) && (!is_writable($propertyFolder) || !is_readable($propertyFolder))) {
            echo "Inmueble #{$propertyRef}: La carpeta no tiene permisos de lectura/escritura, ajustando permisos\n";
            chmod($propertyFolder, 0755);
        }
        
        // Obtener imágenes existentes en la base de datos
        $stmt = $this->db->prepare("SELECT id, url, url_local, orden FROM imagenes WHERE inmueble_id = ?");
        $stmt->execute([$propertyId]);
        $existingImages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($existingImages)) {
            echo "Inmueble #{$propertyRef}: Se encontraron " . count($existingImages) . " imágenes en la base de datos\n";
        }
        
        // Mapear URLs existentes para comparación rápida
        $existingUrlMap = [];
        foreach ($existingImages as $img) {
            $existingUrlMap[$img['url']] = $img;
        }
        
        $processedImages = [];
        
        // Procesar cada imagen
        foreach ($images as $i => $image) {
            $imageUrl = is_array($image) ? ($image['url'] ?? $image['imagen'] ?? $image['src'] ?? '') : $image;
            
            if (empty($imageUrl)) {
                echo "Inmueble #{$propertyRef}: Imagen " . ($i+1) . "/" . count($images) . " no tiene URL\n";
                continue;
            }
            
            try {
                // Obtener extensión del archivo de la URL
                $urlParts = explode('/', $imageUrl);
                $originalFilename = end($urlParts);
                $fileExtension = pathinfo($originalFilename, PATHINFO_EXTENSION);
                if (empty($fileExtension)) {
                    $fileExtension = 'jpg'; // Extensión predeterminada si no se puede determinar
                }
                
                // Crear nombre de archivo local - Siguiendo el formato de SyncOrbisExpress: {propertyRef}_{index}_{originalFilename}
                $localFilename = "{$propertyRef}_" . ($i+1) . "_{$originalFilename}";
                $localPath = $propertyFolder . '/' . $localFilename;
                
                // Asegurarse de que la carpeta exista
                if (!file_exists(dirname($localPath))) {
                    mkdir(dirname($localPath), 0755, true);
                }
                
                $hash = null;
                $imageRegistered = false;
                $existingImageId = null;
                
                // Verificar si la imagen ya existe en la base de datos
                if (isset($existingUrlMap[$imageUrl])) {
                    $existingImage = $existingUrlMap[$imageUrl];
                    $existingImageId = $existingImage['id'];
                    
                    // Si la imagen ya existe y tiene la misma ruta local, no descargarla de nuevo
                    if (file_exists($existingImage['url_local'])) {
                        echo "Inmueble #{$propertyRef}: Imagen " . ($i+1) . "/" . count($images) . " ya existe localmente en {$existingImage['url_local']}\n";
                        
                        // Agregar la imagen a la lista de procesadas con su ID existente
                        $processedImages[] = [
                            'id' => $existingImage['id'],
                            'url' => $imageUrl,
                            'url_local' => $existingImage['url_local'],
                            'orden' => $i,
                            'descargada' => 1
                        ];
                        
                        $imageRegistered = true;
                        continue; // Pasar a la siguiente imagen
                    }
                    
                    echo "Inmueble #{$propertyRef}: Imagen " . ($i+1) . "/" . count($images) . " existe en la base de datos pero no en disco, descargando nuevamente\n";
                }
                
                // Si la imagen no está registrada en la base de datos, verificar si existe en el sistema de archivos
                if (!$imageRegistered && file_exists($localPath)) {
                    // Si la imagen ya existe en disco, calcular su hash
                    $hash = md5_file($localPath);
                    echo "Inmueble #{$propertyRef}: Imagen " . ($i+1) . "/" . count($images) . " ya existe localmente con hash {$hash}\n";
                    
                    $processedImages[] = [
                        'url' => $imageUrl,
                        'url_local' => $localPath,
                        'orden' => $i,
                        'descargada' => 1
                    ];
                    
                    $imageRegistered = true;
                }
                
                // Si la imagen no está registrada y no existe en disco, descargarla
                if (!$imageRegistered) {
                    echo "Inmueble #{$propertyRef}: Descargando imagen " . ($i+1) . "/" . count($images) . ": {$imageUrl}\n";
                    
                    try {
                        // Descargar la imagen
                        $imageData = file_get_contents($imageUrl);
                        
                        if ($imageData === false) {
                            throw new Exception("No se pudo descargar la imagen");
                        }
                        
                        // Guardar la imagen localmente
                        file_put_contents($localPath, $imageData);
                        
                        // Calcular hash MD5 de la imagen
                        $hash = md5_file($localPath);
                        echo "Inmueble #{$propertyRef}: Imagen " . ($i+1) . "/" . count($images) . " descargada con hash {$hash}\n";
                        
                        // Agregar la imagen a la lista de procesadas
                        $processedImages[] = [
                            'url' => $imageUrl,
                            'url_local' => $localPath,
                            'orden' => $i,
                            'descargada' => 1
                        ];
                        
                        // Incrementar contador de imágenes descargadas
                        $this->stats['imagenes_descargadas']++;
                    } catch (Exception $downloadError) {
                        echo "Error al descargar imagen " . ($i+1) . "/" . count($images) . " para inmueble #{$propertyRef}: {$downloadError->getMessage()}\n";
                        continue; // Pasar a la siguiente imagen
                    }
                }
            } catch (Exception $error) {
                echo "Error al procesar imagen " . ($i+1) . "/" . count($images) . " para inmueble #{$propertyRef}: {$error->getMessage()}\n";
            }
        }
        
        // Guardar las imágenes procesadas en la base de datos
        try {
            // Eliminar imágenes existentes que ya no están en la lista actual
            if (!empty($existingImages)) {
                $processedUrls = array_column($processedImages, 'url');
                $imagesToDelete = [];
                
                foreach ($existingImages as $img) {
                    if (!in_array($img['url'], $processedUrls)) {
                        $imagesToDelete[] = $img['id'];
                    }
                }
                
                if (!empty($imagesToDelete)) {
                    echo "Inmueble #{$propertyRef}: Eliminando " . count($imagesToDelete) . " imágenes obsoletas de la base de datos\n";
                    
                    $placeholders = implode(',', array_fill(0, count($imagesToDelete), '?'));
                    $stmt = $this->db->prepare("DELETE FROM imagenes WHERE id IN ({$placeholders})");
                    $stmt->execute($imagesToDelete);
                    
                    $this->stats['imagenes_eliminadas'] += count($imagesToDelete);
                }
            }
            
            // Insertar o actualizar imágenes en la base de datos
            foreach ($processedImages as $img) {
                if (isset($img['id'])) {
                    // Actualizar imagen existente
                    $stmt = $this->db->prepare("
                        UPDATE imagenes SET 
                            orden = ?, 
                            fecha_actualizacion = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$img['orden'], $img['id']]);
                } else {
                    // Preparar datos adicionales para modo Laravel
                    $laravelDisk = null;
                    $laravelPath = null;
                    
                    if ($this->storageMode === 'laravel' && isset($img['laravel_path'])) {
                        $laravelDisk = $this->laravelDisk;
                        $laravelPath = $img['laravel_path'];
                    }
                    
                    // Verificar si la tabla tiene las columnas para Laravel
                    $hasLaravelColumns = $this->checkLaravelColumns();
                    
                    if ($hasLaravelColumns) {
                        // Insertar nueva imagen con información de Laravel
                        $stmt = $this->db->prepare("
                            INSERT INTO imagenes (
                                inmueble_id, url, url_local, orden, descargada, hash, 
                                laravel_disk, laravel_path, fecha_creacion, fecha_actualizacion
                            ) VALUES (
                                ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
                            )
                        ");
                        $stmt->execute([
                            $propertyId,
                            $img['url'],
                            $img['url_local'],
                            $img['orden'],
                            $img['descargada'] ?? 1,
                            $img['hash'] ?? null,
                            $laravelDisk,
                            $laravelPath
                        ]);
                    } else {
                        // Insertar nueva imagen sin información de Laravel
                        $stmt = $this->db->prepare("
                            INSERT INTO imagenes (
                                inmueble_id, url, url_local, orden, descargada, hash, fecha_creacion, fecha_actualizacion
                            ) VALUES (
                                ?, ?, ?, ?, ?, ?, NOW(), NOW()
                            )
                        ");
                        $stmt->execute([
                            $propertyId,
                            $img['url'],
                            $img['url_local'],
                            $img['orden'],
                            $img['descargada'] ?? 1,
                            $img['hash'] ?? null
                        ]);
                    }
                }
            }
            
            echo "Inmueble #{$propertyRef}: " . count($processedImages) . " imágenes guardadas en la base de datos\n";
        } catch (Exception $dbError) {
            echo "Error al guardar imágenes en la base de datos para inmueble #{$propertyRef}: {$dbError->getMessage()}\n";
        }
        
        return $processedImages;
    }
    
    /**
     * Verifica si la tabla de imágenes tiene las columnas necesarias para Laravel
     * 
     * @return bool True si la tabla tiene las columnas para Laravel, false en caso contrario
     */
    private function checkLaravelColumns() {
        try {
            // Verificar si existen las columnas laravel_disk y laravel_path
            $diskColumnExists = $this->db->query("SHOW COLUMNS FROM imagenes LIKE 'laravel_disk'")->rowCount() > 0;
            $pathColumnExists = $this->db->query("SHOW COLUMNS FROM imagenes LIKE 'laravel_path'")->rowCount() > 0;
            
            if (!$diskColumnExists || !$pathColumnExists) {
                // Si no existen las columnas, agregarlas
                if (!$diskColumnExists) {
                    echo "Agregando columna 'laravel_disk' a la tabla 'imagenes'\n";
                    $this->db->exec("ALTER TABLE imagenes ADD COLUMN laravel_disk VARCHAR(50) NULL AFTER url_local");
                }
                
                if (!$pathColumnExists) {
                    echo "Agregando columna 'laravel_path' a la tabla 'imagenes'\n";
                    $this->db->exec("ALTER TABLE imagenes ADD COLUMN laravel_path VARCHAR(255) NULL AFTER laravel_disk");
                }
                
                echo "Columnas para Laravel agregadas correctamente a la tabla 'imagenes'\n";
            }
            
            return true;
        } catch (\Exception $e) {
            echo "Error al verificar/agregar columnas para Laravel: {$e->getMessage()}\n";
            return false;
        }
    }
}
