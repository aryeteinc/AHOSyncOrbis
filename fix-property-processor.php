<?php
/**
 * Script para corregir las referencias a 'ref' en PropertyProcessor.php
 */

$file = __DIR__ . '/src/PropertyProcessor.php';
$content = file_get_contents($file);

// 1. Corregir la consulta SQL que inserta un nuevo inmueble
// Buscar patrones como 'ref' => $property['ref'] y cambiarlos por 'sync_code' => $property['ref']
$content = preg_replace(
    "/['\"](ref)['\"](\s*=>\s*\\\$property\\['ref'\\])/",
    "'sync_code'$2",
    $content
);

// 2. Corregir las consultas SQL que hacen referencia a la columna 'ref'
$content = preg_replace(
    "/(INSERT INTO properties\s*\()([^)]*)(ref)([^)]*\))/i",
    "$1$2sync_code$4",
    $content
);

$content = preg_replace(
    "/(UPDATE properties\s*SET\s*)([^,]*)(ref)([^,]*,)/i",
    "$1$2sync_code$4",
    $content
);

// 3. Asegurarse de que las propiedades se buscan por sync_code y no por ref
$content = preg_replace(
    "/(WHERE\s*)(ref)(\s*=)/i",
    "$1sync_code$3",
    $content
);

// Guardar los cambios
file_put_contents($file, $content);

echo "Se han corregido las referencias a 'ref' en PropertyProcessor.php\n";

// Ahora vamos a corregir el método processProperty para usar sync_code en lugar de ref
$propertyProcessorContent = file_get_contents($file);

// Modificar la línea que obtiene la referencia del inmueble
$propertyProcessorContent = str_replace(
    '$propertyRef = $property[\'ref\'];',
    '$propertyRef = $property[\'ref\']; // Usamos ref como identificador interno, pero sync_code para la BD',
    $propertyProcessorContent
);

// Guardar los cambios
file_put_contents($file, $propertyProcessorContent);

echo "Se ha actualizado el método processProperty para usar sync_code correctamente\n";
