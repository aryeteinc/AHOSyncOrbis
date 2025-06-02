<?php
/**
 * Clase para procesar imágenes de propiedades inmobiliarias con convenciones de Laravel
 */
class ImageProcessorLaravel {
    private $db;
    private $imagesFolder;
    private $stats;
    private $storageMode;
    private $laravelDisk;
    private $laravelImagesPath;
    private $downloadImages;
    
    /**
     * Constructor
     * 
     * @param PDO $db Instancia de la base de datos
     * @param string $imagesFolder Carpeta para las imágenes
     * @param array &$stats Referencia a las estadísticas de sincronización
     * @param bool $downloadImages Si se deben descargar imágenes
     * @param string $storageMode Modo de almacenamiento ('local' o 'laravel')
     * @param string $laravelDisk Nombre del disco de Laravel (generalmente 'public')
     * @param string $laravelImagesPath Ruta relativa dentro del disco de Laravel
     */
    public function __construct($db, $imagesFolder, &$stats, $downloadImages = true, $storageMode = 'laravel', $laravelDisk = 'public', $laravelImagesPath = 'images/inmuebles') {
        $this->db = $db;
        $this->imagesFolder = $imagesFolder;
        $this->stats = &$stats;
        $this->downloadImages = $downloadImages;
        $this->storageMode = $storageMode;
        $this->laravelDisk = $laravelDisk;
        $this->laravelImagesPath = $laravelImagesPath;
    }
    
    /**
     * Procesar imágenes de un inmueble
     * 
     * @param int $propertyId ID del inmueble
     * @param string $propertyRef Referencia del inmueble
     * @param array $images Imágenes a procesar
     * @return array Imágenes procesadas
     */
    public function processImages($propertyId, $propertyRef, $images) {
        if (!$this->downloadImages) {
            echo "Inmueble #{$propertyRef}: Descarga de imágenes desactivada\n";
            return [];
        }
        
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
            // Evitar duplicación de rutas verificando si imagesFolder ya contiene laravelImagesPath
            if (strpos($storageBasePath, rtrim($this->laravelImagesPath, '/')) !== false) {
                $laravelRelativePath = "property_{$propertyRef}";
            } else {
                $laravelRelativePath = rtrim($this->laravelImagesPath, '/') . "/property_{$propertyRef}";
            }
            $propertyFolder = $storageBasePath . '/' . $laravelRelativePath;
            
            echo "Inmueble #{$propertyRef}: Usando modo de almacenamiento Laravel (Disco: {$this->laravelDisk}, Ruta: {$laravelRelativePath})\n";
        } else {
            // En modo local, usar la estructura de directorios tradicional
            $propertyFolder = rtrim($this->imagesFolder, '/') . "/property_{$propertyRef}";
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
        
        // Verificar si existe la tabla de imágenes
        $tableExists = $this->db->query("SHOW TABLES LIKE 'images'")->rowCount() > 0;
        
        if (!$tableExists) {
            echo "Inmueble #{$propertyRef}: No se pueden procesar imágenes - la tabla 'images' no existe\n";
            // Crear la tabla de imágenes con convenciones de Laravel
            $this->db->exec("
                CREATE TABLE images (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    property_id INT NOT NULL,
                    url VARCHAR(255) NOT NULL,
                    local_url VARCHAR(255) NULL,
                    order_num INT DEFAULT 0,
                    is_downloaded TINYINT(1) DEFAULT 0,
                    laravel_disk VARCHAR(50) NULL,
                    laravel_path VARCHAR(255) NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX (property_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            echo "Inmueble #{$propertyRef}: Tabla 'images' creada correctamente\n";
        }
        
        // Obtener imágenes existentes en la base de datos
        $stmt = $this->db->prepare("SELECT id, url, local_url, order_num FROM images WHERE property_id = ?");
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
            $imageUrl = is_array($image) ? ($image['url'] ?? '') : $image;
            
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
                
                // Crear nombre de archivo local - Siguiendo el formato de Laravel: {propertyRef}_{index}_{originalFilename}
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
                    if (file_exists($existingImage['local_url'])) {
                        echo "Inmueble #{$propertyRef}: Imagen " . ($i+1) . "/" . count($images) . " ya existe localmente en {$existingImage['local_url']}\n";
                        
                        // Obtener el hash de la imagen existente si no está en la base de datos
                        if (empty($existingImage['hash'])) {
                            $existingHash = md5_file($existingImage['local_url']);
                            
                            // Actualizar el hash en la base de datos para futuras comparaciones
                            $updateHashStmt = $this->db->prepare("UPDATE images SET hash = ? WHERE id = ?");
                            $updateHashStmt->execute([$existingHash, $existingImage['id']]);
                            
                            echo "Inmueble #{$propertyRef}: Actualizado hash de imagen existente: {$existingHash}\n";
                        }
                        
                        // Agregar la imagen a la lista de procesadas con su ID existente
                        $processedImages[] = [
                            'id' => $existingImage['id'],
                            'url' => $imageUrl,
                            'local_url' => $existingImage['local_url'],
                            'order_num' => $i,
                            'is_downloaded' => 1,
                            'hash' => $existingImage['hash'] ?? md5_file($existingImage['local_url'])
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
                        'local_url' => $localPath,
                        'order_num' => $i,
                        'is_downloaded' => 1
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
                            'local_url' => $localPath,
                            'order_num' => $i,
                            'is_downloaded' => 1
                        ];
                        
                        // Incrementar contador de imágenes descargadas
                        $this->stats['imagenes_descargadas'] = ($this->stats['imagenes_descargadas'] ?? 0) + 1;
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
                    $stmt = $this->db->prepare("DELETE FROM images WHERE id IN ({$placeholders})");
                    $stmt->execute($imagesToDelete);
                    
                    $this->stats['imagenes_eliminadas'] = ($this->stats['imagenes_eliminadas'] ?? 0) + count($imagesToDelete);
                }
            }
            
            // Insertar o actualizar imágenes en la base de datos
            foreach ($processedImages as $img) {
                if (isset($img['id'])) {
                    // Actualizar imagen existente
                    $stmt = $this->db->prepare("
                        UPDATE images SET 
                            order_num = ?, 
                            updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$img['order_num'], $img['id']]);
                } else {
                    // Preparar datos adicionales para modo Laravel
                    $laravelDisk = null;
                    $laravelPath = null;
                    
                    if ($this->storageMode === 'laravel') {
                        $laravelDisk = $this->laravelDisk;
                        
                        // Extraer el nombre del archivo de la ruta local
                        $filename = basename($img['local_url']);
                        
                        // Construir la ruta relativa completa para Laravel
                        if (isset($laravelRelativePath)) {
                            // Obtener la ruta relativa desde storage/app/public
                            // Primero, extraer la parte de la ruta después de storage/app/public
                            $storagePath = rtrim(getenv('LARAVEL_STORAGE_PATH'), '/');
                            $relativePath = '';
                            
                            if (strpos($img['local_url'], $storagePath) === 0) {
                                // Quitar la parte del storage/app/public de la ruta
                                $relativePath = substr($img['local_url'], strlen($storagePath) + 1);
                            } else {
                                // Si no podemos extraer la ruta relativa, construirla manualmente
                                $relativePath = rtrim($laravelRelativePath, '/') . '/' . $filename;
                            }
                            
                            // Asegurarse de que no haya barras diagonales al inicio
                            $laravelPath = ltrim($relativePath, '/');
                        } else {
                            // Si no hay ruta relativa, usar solo el nombre del archivo
                            $laravelPath = $filename;
                        }
                    }
                    
                    // Determinar si esta imagen debe ser destacada (si es la primera o tiene order_num = 0)
                    $isFeatured = 0;
                    if ($img['order_num'] === 0) {
                        // Verificar si ya existe alguna imagen destacada para esta propiedad
                        $featuredExists = $this->db->prepare("SELECT COUNT(*) FROM images WHERE property_id = ? AND is_featured = 1");
                        $featuredExists->execute([$propertyId]);
                        if ($featuredExists->fetchColumn() == 0) {
                            // Si no hay imagen destacada, marcar esta como destacada
                            $isFeatured = 1;
                        }
                    }
                    
                    // Insertar nueva imagen
                    $stmt = $this->db->prepare("
                        INSERT INTO images (
                            property_id, url, local_url, order_num, is_downloaded, is_featured,
                            laravel_disk, laravel_path, created_at, updated_at
                        ) VALUES (
                            ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
                        )
                    ");
                    $stmt->execute([
                        $propertyId,
                        $img['url'],
                        $img['local_url'],
                        $img['order_num'],
                        $img['is_downloaded'] ?? 1,
                        $isFeatured,
                        $laravelDisk,
                        $laravelPath
                    ]);
                }
            }
            
            echo "Inmueble #{$propertyRef}: " . count($processedImages) . " imágenes guardadas en la base de datos\n";
        } catch (Exception $dbError) {
            echo "Error al guardar imágenes en la base de datos para inmueble #{$propertyRef}: {$dbError->getMessage()}\n";
        }
        
        return $processedImages;
    }
}
