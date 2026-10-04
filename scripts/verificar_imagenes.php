<?php
/**
 * Revisa que las imágenes referenciadas en MongoDB existan en el disco.
 *
 * Uso:  php scripts/verificar_imagenes.php            (solo revisa y muestra un informe)
 *       php scripts/verificar_imagenes.php --reparar  (corrige referencias a imágenes duplicadas eliminadas)
 *
 * En la limpieza de imágenes se eliminaron copias duplicadas con nombre legible
 * (p. ej. "imagenes/Techopvc.jpeg") porque existía el mismo archivo exacto con el
 * nombre que generó el panel al subir el producto (p. ej. "imagenes/1779703640_6a141f58c7583.jpeg").
 * Si algún documento apuntaba al nombre eliminado, --reparar lo cambia por su copia idéntica.
 */
if (PHP_SAPI !== 'cli') {
    exit("Solo se puede ejecutar desde la línea de comandos.\n");
}
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/imagenes_duplicadas.php';

const DUPLICADOS_ELIMINADOS = IMAGENES_DUPLICADAS;

$reparar = in_array('--reparar', $argv, true);
$total = 0;
$faltantes = 0;
$reparados = 0;
foreach (['productos' => 'nombre', 'pedido_detalle' => 'nombre_producto'] as $coleccion => $campoNombre) {
    $col = mongo()->selectCollection($coleccion);
    foreach ($col->find(['imagen' => ['$exists' => true, '$ne' => '']], ['projection' => ['imagen' => 1, $campoNombre => 1]]) as $doc) {
        $total++;
        $ruta = ltrim(str_replace('\\', '/', (string)$doc['imagen']), '/');
        if (is_file(CS_ROOT . '/' . $ruta)) {
            continue;
        }
        if (isset(DUPLICADOS_ELIMINADOS[$ruta]) && $reparar) {
            $col->updateOne(['_id' => $doc['_id']], ['$set' => ['imagen' => DUPLICADOS_ELIMINADOS[$ruta]]]);
            $reparados++;
            echo "✔ $coleccion «{$doc[$campoNombre]}»: $ruta → " . DUPLICADOS_ELIMINADOS[$ruta] . "\n";
            continue;
        }
        $faltantes++;
        echo (isset(DUPLICADOS_ELIMINADOS[$ruta]) ? '↻' : '✘') . " $coleccion «{$doc[$campoNombre]}»: no existe $ruta"
            . (isset(DUPLICADOS_ELIMINADOS[$ruta]) ? ' (se puede reparar con --reparar)' : ' (vuelve a subir la imagen desde el panel)') . "\n";
    }
}
echo "\nRevisadas: $total · reparadas: $reparados · con problema: $faltantes\n";
if ($faltantes === 0) {
    echo "Todas las imágenes referenciadas existen.\n";
}
