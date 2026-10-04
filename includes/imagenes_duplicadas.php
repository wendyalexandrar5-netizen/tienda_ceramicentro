<?php
/**
 * Imágenes duplicadas que se eliminaron en la limpieza (nombre legible => copia idéntica que
 * generó el panel al subir el producto). Si un documento antiguo de MongoDB todavía apunta al
 * nombre eliminado, la web y la API muestran automáticamente la copia idéntica.
 * Para corregir la base de datos: php scripts/verificar_imagenes.php --reparar
 */
const IMAGENES_DUPLICADAS = [
    "imagenes/Baño.jpg" => "imagenes/1763991179_69245e8b7c59e.jpg",
    "imagenes/Techopvc.jpeg" => "imagenes/1779703640_6a141f58c7583.jpeg",
    "imagenes/Techopvcblanco.jpg" => "imagenes/1779703480_6a141eb8a2f0c.jpg",
    "imagenes/Techopvcmadera.jpg" => "imagenes/1779703687_6a141f8759b13.jpg",
    "imagenes/Techopvcolas.jpg" => "imagenes/1779703568_6a141f107b782.jpg",
    "imagenes/Techopvcpino.jpg" => "imagenes/1779703724_6a141fac65505.jpg",
    "imagenes/bañoazul.webp" => "imagenes/1763991133_69245e5d8e849.webp",
    "imagenes/bañonegro.webp" => "imagenes/1763991220_69245eb40f001.webp",
    "imagenes/clasica.jpg" => "imagenes/1763968011_6924040b64051.jpg",
    "imagenes/esmeraldas.jpg" => "imagenes/1763967785_692403293174a.jpg",
    "imagenes/gres.jpg" => "imagenes/1763968077_6924044df3278.jpg",
    "imagenes/hexagonal.png" => "imagenes/1763968129_692404811b914.png",
    "imagenes/lavamanos.jpg" => "imagenes/1763991340_69245f2c2d567.jpg",
    "imagenes/lavamanosazul.jpg" => "imagenes/1763991274_69245eeaf0771.jpg",
    "imagenes/lavamanosnegro.jpg" => "imagenes/1763991400_69245f68c13ff.jpg",
    "imagenes/marmol.jpg" => "imagenes/1763967740_692402fc2d147.jpg",
    "imagenes/mosaico.webp" => "imagenes/1763967411_692401b3824b7.webp",
    "imagenes/panelcuadrado.jpg" => "imagenes/1779867351_6a169ed79e4f8.jpg",
    "imagenes/panelredondo.jpg" => "imagenes/1779867390_6a169efeb6cf3.jpg",
    "imagenes/pisocafe.webp" => "imagenes/1779726564_6a1478e47bc26.webp",
    "imagenes/pisogris.webp" => "imagenes/1779866208_6a169a6048b4d.webp",
    "imagenes/pisomadera.webp" => "imagenes/1779726167_6a147757b3620.webp",
    "imagenes/pisomiel.webp" => "imagenes/1779866565_6a169bc53bf6f.webp",
    "imagenes/regaderacircularnegra.webp" => "imagenes/1779708768_6a1433606a724.webp",
    "imagenes/regaderacircularplateada.jpg" => "imagenes/1779708963_6a1434230311e.jpg",
    "imagenes/regaderacuadradanegra.webp" => "imagenes/1779867532_6a169f8cd4a80.webp",
    "imagenes/regaderacuadradaplateada.jpg" => "imagenes/1779867583_6a169fbfb527e.jpg",
    "imagenes/supermastick1.4gln.jpg" => "imagenes/1779867219_6a169e5362f8e.jpg",
    "imagenes/supermastick2gln.jpg" => "imagenes/1779867173_6a169e25abef4.jpg",
    "imagenes/supermastick4.5gln.jpg" => "imagenes/1779867117_6a169ded3a385.jpg",
    "imagenes/vectormoradoblanco.jpg" => "imagenes/1763967616_69240280b8cb4.jpg",
    "imagenes/vintage.jpg" => "imagenes/1763967668_692402b48a11f.jpg",
];

/** Devuelve la ruta que existe en disco (o la original si no hay reemplazo). */
function imagen_resolver(string $ruta): string
{
    if ($ruta !== '' && !is_file(CS_ROOT . '/' . $ruta) && isset(IMAGENES_DUPLICADAS[$ruta])) {
        return IMAGENES_DUPLICADAS[$ruta];
    }
    return $ruta;
}
