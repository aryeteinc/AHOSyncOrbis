<?php
/**
 * Clase para sincronizar imágenes de propiedades, detectando cambios, actualizaciones y eliminaciones
 */
class ImageSynchronizer {
    private $db;
    private $imagesFolder;
    private $imageProcessor;
    private $stats;
    
    /**
     * Constructor
     * 
     * @param PDO $db Instancia de la base de datos
     * @param string $imagesFolder Carpeta para las imágenes
     * @param ImageProcessorLaravel $imageProcessor Procesador de imágenes
     * @param array &$stats Referencia a las estadísticas de sincronización
     */
    public function __construct($db, $imagesFolder, $imageProcessor, &$stats) {
        $this->db = $db;
        $this->imagesFolder = $imagesFolder;
        $this->imageProcessor = $imageProcessor;
        $this->stats = &$stats;
        
        // Inicializar estadísticas si no existen
        if (!isset($this->stats['imagenes_actualizadas'])) {
            $this->stats['imagenes_actualizadas'] = 0;
        }
        if (!isset($this->stats['imagenes_eliminadas_fisicamente'])) {
            $this->stats['imagenes_eliminadas_fisicamente'] = 0;
        }
    }
    
    /**
     * Sincronizar imágenes de una propiedad
     * 
     * @param int $propertyId ID de la propiedad
     * @param string $propertyRef Referencia de la propiedad
     * @param array $newImages Nuevas imágenes desde la API
     * @return array Resultado de la sincronización
     */
    public function synchronizeImages($propertyId, $propertyRef, $newImages) {
        echo "Sincronizando imágenes para propiedad #{$propertyRef}...\n";
        
        // Obtener imágenes existentes en la base de datos
        $existingImages = $this->getExistingImages($propertyId);
        
        // Si no hay imágenes nuevas pero hay existentes, eliminar todas las existentes
        if (empty($newImages) && !empty($existingImages)) {
            echo "Propiedad #{$propertyRef}: No hay imágenes nuevas, eliminando todas las existentes\n";
            $this->deleteAllPropertyImages($propertyId, $propertyRef);
            return ['deleted' => count($existingImages), 'added' => 0, 'updated' => 0];
        }
        
        // Si no hay imágenes nuevas ni existentes, no hacer nada
        if (empty($newImages) && empty($existingImages)) {
            echo "Propiedad #{$propertyRef}: No hay imágenes para sincronizar\n";
            return ['deleted' => 0, 'added' => 0, 'updated' => 0];
        }
        
        // Procesar las nuevas imágenes
        $processedImages = $this->imageProcessor->processImages($propertyId, $propertyRef, $newImages);
        
        // Identificar imágenes que ya no existen en la API
        $newImageUrls = array_column($processedImages, 'url');
        $imagesToDelete = [];
        
        foreach ($existingImages as $img) {
            if (!in_array($img['url'], $newImageUrls)) {
                $imagesToDelete[] = $img;
            }
        }
        
        // Eliminar imágenes que ya no existen en la API
        $deletedCount = $this->deleteObsoleteImages($imagesToDelete, $propertyRef);
        
        // Identificar imágenes actualizadas (misma URL pero diferente contenido)
        $updatedCount = $this->detectAndUpdateChangedImages($propertyId, $propertyRef, $newImages, $existingImages);
        
        // Calcular imágenes añadidas (nuevas URLs)
        $addedCount = count($processedImages) - count(array_intersect($newImageUrls, array_column($existingImages, 'url')));
        
        echo "Propiedad #{$propertyRef}: Sincronización completada - {$addedCount} añadidas, {$updatedCount} actualizadas, {$deletedCount} eliminadas\n";
        
        return [
            'deleted' => $deletedCount,
            'added' => $addedCount,
            'updated' => $updatedCount
        ];
    }
    
    /**
     * Obtener imágenes existentes de una propiedad
     * 
     * @param int $propertyId ID de la propiedad
     * @return array Imágenes existentes
     */
    private function getExistingImages($propertyId) {
        $stmt = $this->db->prepare("
            SELECT id, url, local_url, order_num, laravel_disk, laravel_path 
            FROM images 
            WHERE property_id = ?
        ");
        $stmt->execute([$propertyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Eliminar imágenes obsoletas (que ya no existen en la API)
     * 
     * @param array $imagesToDelete Imágenes a eliminar
     * @param string $propertyRef Referencia de la propiedad
     * @return int Número de imágenes eliminadas
     */
    private function deleteObsoleteImages($imagesToDelete, $propertyRef) {
        if (empty($imagesToDelete)) {
            return 0;
        }
        
        echo "Propiedad #{$propertyRef}: Eliminando " . count($imagesToDelete) . " imágenes obsoletas\n";
        
        $imageIds = array_column($imagesToDelete, 'id');
        $placeholders = implode(',', array_fill(0, count($imageIds), '?'));
        
        // Verificar si alguna imagen destacada será eliminada
        $featuredImageWillBeDeleted = false;
        $propertyId = null;
        
        foreach ($imagesToDelete as $img) {
            if (isset($img['is_featured']) && $img['is_featured'] == 1) {
                $featuredImageWillBeDeleted = true;
                $propertyId = $img['property_id'];
                break;
            }
        }
        
        // Eliminar archivos físicos
        foreach ($imagesToDelete as $img) {
            if (!empty($img['local_url']) && file_exists($img['local_url'])) {
                echo "Propiedad #{$propertyRef}: Eliminando archivo físico: {$img['local_url']}\n";
                unlink($img['local_url']);
                $this->stats['imagenes_eliminadas_fisicamente']++;
            }
        }
        
        // Eliminar registros de la base de datos
        $stmt = $this->db->prepare("DELETE FROM images WHERE id IN ({$placeholders})");
        $stmt->execute($imageIds);
        
        $deletedCount = $stmt->rowCount();
        $this->stats['imagenes_eliminadas'] = ($this->stats['imagenes_eliminadas'] ?? 0) + $deletedCount;
        
        // Si se eliminó la imagen destacada, asignar otra imagen como destacada
        if ($featuredImageWillBeDeleted && $propertyId) {
            $this->assignNewFeaturedImage($propertyId, $propertyRef);
        }
        
        // Verificar si la carpeta de la propiedad está vacía y eliminarla si es así
        $this->cleanEmptyDirectories($propertyRef);
        
        return $deletedCount;
    }
    
    /**
     * Detectar y actualizar imágenes que han cambiado (misma URL pero contenido diferente)
     * 
     * @param int $propertyId ID de la propiedad
     * @param string $propertyRef Referencia de la propiedad
     * @param array $newImages Nuevas imágenes desde la API
     * @param array $existingImages Imágenes existentes
     * @return int Número de imágenes actualizadas
     */
    private function detectAndUpdateChangedImages($propertyId, $propertyRef, $newImages, $existingImages) {
        $updatedCount = 0;
        $existingUrlMap = [];
        $featuredImageId = null;
        
        // Crear mapa de URLs existentes para búsqueda rápida y encontrar la imagen destacada
        foreach ($existingImages as $img) {
            $existingUrlMap[$img['url']] = $img;
            
            // Guardar el ID de la imagen destacada si existe
            if (isset($img['is_featured']) && $img['is_featured'] == 1) {
                $featuredImageId = $img['id'];
            }
        }
        
        // Verificar cada imagen nueva
        foreach ($newImages as $i => $image) {
            $imageUrl = is_array($image) ? ($image['url'] ?? '') : $image;
            
            if (empty($imageUrl) || !isset($existingUrlMap[$imageUrl])) {
                continue; // No es una imagen existente, se procesará como nueva
            }
            
            $existingImage = $existingUrlMap[$imageUrl];
            $localPath = $existingImage['local_url'];
            
            // Si el archivo local no existe, se descargará como nuevo
            if (empty($localPath) || !file_exists($localPath)) {
                continue;
            }
            
            try {
                // Descargar la imagen temporalmente para comparar
                $tempFile = tempnam(sys_get_temp_dir(), 'img_');
                $imageData = @file_get_contents($imageUrl);
                
                if ($imageData === false) {
                    echo "Propiedad #{$propertyRef}: No se pudo descargar la imagen para comparación: {$imageUrl}\n";
                    continue;
                }
                
                file_put_contents($tempFile, $imageData);
                
                // Calcular hashes para comparar
                $existingHash = md5_file($localPath);
                $newHash = md5_file($tempFile);
                
                // Si los hashes son diferentes, la imagen ha cambiado
                if ($existingHash !== $newHash) {
                    echo "Propiedad #{$propertyRef}: Imagen actualizada detectada: {$imageUrl}\n";
                    echo "  Hash anterior: {$existingHash}\n";
                    echo "  Hash nuevo: {$newHash}\n";
                    
                    // Reemplazar la imagen existente con la nueva
                    file_put_contents($localPath, $imageData);
                    
                    // Actualizar registro en la base de datos
                    $stmt = $this->db->prepare("
                        UPDATE images 
                        SET updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$existingImage['id']]);
                    
                    $updatedCount++;
                    $this->stats['imagenes_actualizadas']++;
                }
                
                // Eliminar archivo temporal
                @unlink($tempFile);
                
            } catch (Exception $e) {
                echo "Propiedad #{$propertyRef}: Error al verificar actualización de imagen: {$e->getMessage()}\n";
            }
        }
        
        return $updatedCount;
    }
    
    /**
     * Eliminar todas las imágenes de una propiedad
     * 
     * @param int $propertyId ID de la propiedad
     * @param string $propertyRef Referencia de la propiedad
     * @return int Número de imágenes eliminadas
     */
    public function deleteAllPropertyImages($propertyId, $propertyRef) {
        // Obtener todas las imágenes de la propiedad
        $stmt = $this->db->prepare("SELECT id, local_url FROM images WHERE property_id = ?");
        $stmt->execute([$propertyId]);
        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($images)) {
            echo "Propiedad #{$propertyRef}: No hay imágenes para eliminar\n";
            return 0;
        }
        
        echo "Propiedad #{$propertyRef}: Eliminando todas las imágenes (" . count($images) . ")\n";
        
        // Eliminar archivos físicos
        foreach ($images as $img) {
            if (!empty($img['local_url']) && file_exists($img['local_url'])) {
                echo "Propiedad #{$propertyRef}: Eliminando archivo físico: {$img['local_url']}\n";
                unlink($img['local_url']);
                $this->stats['imagenes_eliminadas_fisicamente']++;
            }
        }
        
        // Eliminar registros de la base de datos
        $stmt = $this->db->prepare("DELETE FROM images WHERE property_id = ?");
        $stmt->execute([$propertyId]);
        
        $deletedCount = $stmt->rowCount();
        $this->stats['imagenes_eliminadas'] = ($this->stats['imagenes_eliminadas'] ?? 0) + $deletedCount;
        
        // Limpiar directorios vacíos
        $this->cleanEmptyDirectories($propertyRef);
        
        return $deletedCount;
    }
    
    /**
     * Asignar una nueva imagen destacada cuando la imagen destacada actual es eliminada
     * 
     * @param int $propertyId ID de la propiedad
     * @param string $propertyRef Referencia de la propiedad
     */
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
    
    /**
     * Limpiar directorios vacíos después de eliminar imágenes
     * 
     * @param string $propertyRef Referencia de la propiedad
     */
    private function cleanEmptyDirectories($propertyRef) {
        // Buscar posibles carpetas de la propiedad
        $propertyFolder = rtrim($this->imagesFolder, '/') . "/property_{$propertyRef}";
        $propertyFolderAlt = rtrim($this->imagesFolder, '/') . "/{$propertyRef}";
        
        // Verificar la carpeta con formato property_XXX
        if (is_dir($propertyFolder)) {
            // Verificar si la carpeta está vacía
            $files = scandir($propertyFolder);
            $isEmpty = count($files) <= 2; // Solo . y ..
            
            if ($isEmpty) {
                echo "Propiedad #{$propertyRef}: Eliminando carpeta vacía: {$propertyFolder}\n";
                rmdir($propertyFolder);
            }
        }
        
        // Verificar la carpeta con formato XXX (solo referencia)
        if (is_dir($propertyFolderAlt)) {
            // Verificar si la carpeta está vacía
            $files = scandir($propertyFolderAlt);
            $isEmpty = count($files) <= 2; // Solo . y ..
            
            if ($isEmpty) {
                echo "Propiedad #{$propertyRef}: Eliminando carpeta vacía: {$propertyFolderAlt}\n";
                rmdir($propertyFolderAlt);
            }
        }
    }
}
