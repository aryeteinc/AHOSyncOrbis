<?php
/**
 * Script para eliminar referencias a total_price en PropertyProcessor.php
 */

$file = __DIR__ . '/../../src/PropertyProcessor.php';
$content = file_get_contents($file);

// Eliminar la línea 'total_price' => $property['total_price'] ?? ($property['sale_price'] ?? 0),
$content = preg_replace("/\s+'total_price' => .*?,\n/", "\n            // 'total_price' eliminado (no existe en la tabla properties según convenciones Laravel)\n", $content);

// Guardar los cambios
file_put_contents($file, $content);

echo "Referencias a total_price eliminadas del archivo PropertyProcessor.php\n";
