<?php
/**
 * Genera versiones WebP optimizadas (máx. 1600 px) junto a las imágenes JPG/PNG existentes
 * de las carpetas img/ e imagenes/. NO borra ni reemplaza los archivos originales:
 * el sitio usa automáticamente la versión .webp cuando existe (con respaldo al original).
 *
 * Requiere la extensión GD con soporte WebP.
 * Uso:  php scripts/optimizar_imagenes.php            (solo crea las que faltan)
 *       php scripts/optimizar_imagenes.php --forzar   (regenera todas)
 */
if (PHP_SAPI !== 'cli') {
    exit("Solo se puede ejecutar desde la línea de comandos.\n");
}
if (!function_exists('imagewebp')) {
    exit("La extensión GD con soporte WebP no está disponible en este PHP.\n");
}
$raiz = dirname(__DIR__);
$forzar = in_array('--forzar', $argv, true);
$total = 0;
$ahorro = 0;
foreach (['img', 'imagenes'] as $carpeta) {
    foreach (glob($raiz . '/' . $carpeta . '/*.{jpg,jpeg,JPG,JPEG,png,PNG}', GLOB_BRACE) ?: [] as $archivo) {
        $destino = preg_replace('/\.(jpe?g|png)$/i', '.webp', $archivo);
        if (!$forzar && is_file($destino)) {
            continue;
        }
        $info = @getimagesize($archivo);
        if (!$info) {
            echo "✘ No es una imagen válida: $archivo\n";
            continue;
        }
        $img = $info[2] === IMAGETYPE_PNG ? @imagecreatefrompng($archivo) : @imagecreatefromjpeg($archivo);
        if (!$img) {
            echo "✘ No se pudo leer: $archivo\n";
            continue;
        }
        imagepalettetotruecolor($img);
        imagealphablending($img, true);
        imagesavealpha($img, true);
        $w = imagesx($img);
        $h = imagesy($img);
        $max = 1600;
        if (max($w, $h) > $max) {
            $esc = $max / max($w, $h);
            $nw = (int)round($w * $esc);
            $nh = (int)round($h * $esc);
            $nuevo = imagecreatetruecolor($nw, $nh);
            imagealphablending($nuevo, false);
            imagesavealpha($nuevo, true);
            imagecopyresampled($nuevo, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($img);
            $img = $nuevo;
        }
        if (imagewebp($img, $destino, 80)) {
            $antes = filesize($archivo);
            $despues = filesize($destino);
            if ($despues >= $antes) {
                // No aporta: se elimina la copia para que se siga usando el original
                unlink($destino);
                echo "= Sin mejora, se conserva el original: " . basename($archivo) . "\n";
            } else {
                $total++;
                $ahorro += $antes - $despues;
                printf("✔ %s → %s (%d KB → %d KB)\n", basename($archivo), basename($destino), $antes / 1024, $despues / 1024);
            }
        }
        imagedestroy($img);
    }
}
printf("Listo: %d imágenes optimizadas, ahorro aproximado %.1f MB.\n", $total, $ahorro / 1048576);
