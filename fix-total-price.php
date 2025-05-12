<?php
/**
 * Script para eliminar referencias a total_price en PropertyProcessor.php
 */

$file = __DIR__ . '/src/PropertyProcessor.php';
echo "Buscando archivo en: $file\n";

if (!file_exists($file)) {
    echo "ERROR: El archivo no existe en la ruta especificada.\n";
    exit(1);
}

$content = file_get_contents($file);
echo "Contenido original leído: " . strlen($content) . " bytes\n";

// Eliminar la línea 'total_price' => $property['total_price'] ?? ($property['sale_price'] ?? 0),
$newContent = preg_replace("/\s+'total_price' => .*?,\n/", "\n            // 'total_price' eliminado (no existe en la tabla properties según convenciones Laravel)\n", $content);

if ($content === $newContent) {
    echo "ADVERTENCIA: No se encontraron coincidencias para reemplazar.\n";
    
    // Mostrar las líneas que contienen 'total_price'
    $lines = explode("\n", $content);
    foreach ($lines as $i => $line) {
        if (strpos($line, 'total_price') !== false) {
            echo "Línea " . ($i + 1) . ": $line\n";
        }
    }
} else {
    // Guardar los cambios
    file_put_contents($file, $newContent);
    echo "Referencias a total_price eliminadas del archivo PropertyProcessor.php\n";
}
