<?php
/**
 * Catálogo, imágenes y carrito de compras (web).
 * Colecciones: productos, categorias.
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/imagenes_duplicadas.php';

use MongoDB\BSON\ObjectId;

/* ================================================================
 * CATEGORÍAS
 * ================================================================ */

/** @return array<int,array{id:string,nombre:string,descripcion:string}> */
function categorias_todas(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (mongo()->selectCollection('categorias')->find([], ['sort' => ['nombre' => 1]]) as $c) {
            $cache[] = [
                'id'          => (string)$c['_id'],
                'nombre'      => (string)($c['nombre'] ?? 'Sin nombre'),
                'descripcion' => (string)($c['descripcion'] ?? ''),
            ];
        }
    }
    return $cache;
}

/** @return array<string,string> id => nombre */
function categorias_mapa(): array
{
    $mapa = [];
    foreach (categorias_todas() as $c) {
        $mapa[$c['id']] = $c['nombre'];
    }
    return $mapa;
}

function categoria_por_id(string $id): ?array
{
    foreach (categorias_todas() as $c) {
        if ($c['id'] === $id) {
            return $c;
        }
    }
    return null;
}

/* ================================================================
 * PRODUCTOS
 * ================================================================ */

function producto_por_id($id): ?array
{
    $oid = oid($id);
    if (!$oid) {
        return null;
    }
    $p = mongo()->selectCollection('productos')->findOne(['_id' => $oid]);
    return $p ? (array)$p : null;
}

/** Normaliza un documento de producto para mostrarlo. */
function producto_vista(array $p): array
{
    $mapa = categorias_mapa();
    $catId = isset($p['categoria_id']) ? (string)$p['categoria_id'] : '';
    return [
        'id'           => (string)$p['_id'],
        'nombre'       => (string)($p['nombre'] ?? 'Producto'),
        'descripcion'  => (string)($p['descripcion'] ?? ''),
        'precio'       => (float)($p['precio'] ?? 0),
        'stock'        => max(0, (int)($p['stock'] ?? 0)),
        'imagen'       => (string)($p['imagen'] ?? ''),
        'categoria_id' => $catId,
        'categoria'    => $mapa[$catId] ?? 'Sin categoría',
    ];
}

/**
 * Busca productos con filtros, orden y paginación.
 * @return array{items:array,total:int,paginas:int,pagina:int}
 */
function productos_buscar(array $opc = []): array
{
    $q         = trim((string)($opc['q'] ?? ''));
    $categoria = (string)($opc['categoria'] ?? '');
    $orden     = (string)($opc['orden'] ?? 'nombre');
    $porPagina = max(1, min(60, (int)($opc['por_pagina'] ?? 24)));
    $pagina    = max(1, (int)($opc['pagina'] ?? 1));

    $filtro = [];
    if ($q !== '') {
        $rx = regex_literal($q);
        $filtro['$or'] = [
            ['nombre' => ['$regex' => $rx, '$options' => 'i']],
            ['descripcion' => ['$regex' => $rx, '$options' => 'i']],
        ];
    }
    if ($categoria !== '' && ($cat = oid($categoria))) {
        $filtro['categoria_id'] = $cat;
    }
    if (!empty($opc['solo_disponibles'])) {
        $filtro['stock'] = ['$gt' => 0];
    }

    $ordenes = [
        'nombre'      => ['nombre' => 1],
        'precio_asc'  => ['precio' => 1, 'nombre' => 1],
        'precio_desc' => ['precio' => -1, 'nombre' => 1],
        'recientes'   => ['_id' => -1],
    ];
    $sort = $ordenes[$orden] ?? $ordenes['nombre'];

    $col   = mongo()->selectCollection('productos');
    $total = $col->countDocuments($filtro);
    $paginas = max(1, (int)ceil($total / $porPagina));
    $pagina  = min($pagina, $paginas);

    $items = [];
    foreach ($col->find($filtro, ['sort' => $sort, 'skip' => ($pagina - 1) * $porPagina, 'limit' => $porPagina]) as $p) {
        $items[] = producto_vista((array)$p);
    }
    return ['items' => $items, 'total' => $total, 'paginas' => $paginas, 'pagina' => $pagina];
}

/* ================================================================
 * IMÁGENES
 * ================================================================ */

/** Ruta segura de una imagen almacenada (siempre relativa a la carpeta del sitio). */
function imagen_ruta_segura(string $ruta): string
{
    $ruta = str_replace('\\', '/', trim($ruta));
    if ($ruta === '' || strpos($ruta, '..') !== false || preg_match('#^[a-z]+:#i', $ruta)) {
        return '';
    }
    return imagen_resolver(ltrim($ruta, '/'));
}

/**
 * Etiqueta <img> (o <picture> si existe una versión .webp optimizada al lado del original).
 * Incluye dimensiones, carga diferida y texto alternativo.
 */
function imagen_html(string $ruta, string $alt, array $attr = []): string
{
    $ruta = imagen_ruta_segura($ruta);
    $attr += ['loading' => 'lazy', 'decoding' => 'async'];
    if ($ruta === '' || !is_file(CS_ROOT . '/' . $ruta)) {
        $attr['class'] = trim(($attr['class'] ?? '') . ' img-placeholder');
        $src = url('assets/img/sin-imagen.svg');
    } else {
        $src = url(implode('/', array_map('rawurlencode', explode('/', $ruta))));
    }
    $extra = '';
    foreach ($attr as $k => $v) {
        $extra .= ' ' . e($k) . '="' . e($v) . '"';
    }
    $img = '<img src="' . e($src) . '" alt="' . e($alt) . '"' . $extra . '>';

    if ($ruta !== '' && !preg_match('/\.webp$/i', $ruta)) {
        $webp = preg_replace('/\.(jpe?g|png)$/i', '.webp', $ruta);
        if ($webp !== $ruta && is_file(CS_ROOT . '/' . $webp)) {
            $srcWebp = url(implode('/', array_map('rawurlencode', explode('/', $webp))));
            return '<picture><source srcset="' . e($srcWebp) . '" type="image/webp">' . $img . '</picture>';
        }
    }
    return $img;
}

/** URL absoluta de una imagen (Open Graph, JSON-LD, API). */
function imagen_url_absoluta(string $ruta): string
{
    $ruta = imagen_ruta_segura($ruta);
    if ($ruta === '') {
        return url_absoluta('assets/img/sin-imagen.svg');
    }
    return url_absoluta(implode('/', array_map('rawurlencode', explode('/', $ruta))));
}

/**
 * Guarda una imagen subida validando que sea realmente una imagen.
 * Si GD está disponible la redimensiona (máx. 1200 px) y la guarda en WebP.
 * @return array{ok:bool,ruta?:string,error?:string}
 */
function guardar_imagen_subida(array $archivo): array
{
    if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errores = [
            UPLOAD_ERR_INI_SIZE  => 'La imagen supera el tamaño máximo permitido por el servidor.',
            UPLOAD_ERR_FORM_SIZE => 'La imagen supera el tamaño máximo permitido.',
            UPLOAD_ERR_PARTIAL   => 'La imagen se subió de forma incompleta. Intenta de nuevo.',
            UPLOAD_ERR_NO_FILE   => 'Debes seleccionar una imagen.',
        ];
        return ['ok' => false, 'error' => $errores[$archivo['error'] ?? UPLOAD_ERR_NO_FILE] ?? 'No se pudo subir la imagen.'];
    }
    if (($archivo['size'] ?? 0) > 8 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'La imagen no puede superar 8 MB.'];
    }
    $info = @getimagesize($archivo['tmp_name']);
    $tipos = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($tipos[$info[2]])) {
        return ['ok' => false, 'error' => 'El archivo no es una imagen válida (usa JPG, PNG, GIF o WebP).'];
    }
    $ext = $tipos[$info[2]];
    $dir = CS_ROOT . '/imagenes';
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        return ['ok' => false, 'error' => 'No se pudo preparar la carpeta de imágenes.'];
    }
    $base = time() . '_' . bin2hex(random_bytes(6));

    // Optimización con GD (si está disponible): máx. 1200 px y formato WebP
    if (function_exists('imagewebp') && $ext !== 'gif') {
        $origen = match ($ext) {
            'jpg'  => @imagecreatefromjpeg($archivo['tmp_name']),
            'png'  => @imagecreatefrompng($archivo['tmp_name']),
            'webp' => @imagecreatefromwebp($archivo['tmp_name']),
            default => false,
        };
        if ($origen) {
            $img = redimensionar_gd($origen, 1200);
            $destino = $dir . '/' . $base . '.webp';
            if (@imagewebp($img, $destino, 82)) {
                imagedestroy($img);
                return ['ok' => true, 'ruta' => 'imagenes/' . $base . '.webp'];
            }
            imagedestroy($img);
        }
    }

    $destino = $dir . '/' . $base . '.' . $ext;
    if (!move_uploaded_file($archivo['tmp_name'], $destino)) {
        return ['ok' => false, 'error' => 'No se pudo guardar la imagen en el servidor.'];
    }
    return ['ok' => true, 'ruta' => 'imagenes/' . $base . '.' . $ext];
}

/** Redimensiona un recurso GD manteniendo proporción y transparencia. */
function redimensionar_gd($origen, int $maximo)
{
    $w = imagesx($origen);
    $h = imagesy($origen);
    $escala = min(1, $maximo / max($w, $h));
    if ($escala >= 1) {
        imagepalettetotruecolor($origen);
        imagealphablending($origen, true);
        imagesavealpha($origen, true);
        return $origen;
    }
    $nw = max(1, (int)round($w * $escala));
    $nh = max(1, (int)round($h * $escala));
    $nuevo = imagecreatetruecolor($nw, $nh);
    imagealphablending($nuevo, false);
    imagesavealpha($nuevo, true);
    imagecopyresampled($nuevo, $origen, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($origen);
    return $nuevo;
}

/* ================================================================
 * CARRITO (sesión web). Estructura compatible con la versión anterior:
 * $_SESSION['carrito'][producto_id] = [nombre, precio, imagen, cantidad]
 * ================================================================ */

function carrito_items(): array
{
    return is_array($_SESSION['carrito'] ?? null) ? $_SESSION['carrito'] : [];
}

function carrito_contar(): int
{
    $n = 0;
    foreach (carrito_items() as $item) {
        $n += (int)($item['cantidad'] ?? 0);
    }
    return $n;
}

/**
 * Agrega un producto al carrito validando existencia y stock.
 * @return array{ok:bool,mensaje:string,cantidad?:int}
 */
function carrito_agregar(string $productoId, int $cantidad): array
{
    $p = producto_por_id($productoId);
    if (!$p) {
        return ['ok' => false, 'mensaje' => 'El producto ya no existe o no está disponible.'];
    }
    $stock = (int)($p['stock'] ?? 0);
    if ($stock <= 0) {
        return ['ok' => false, 'mensaje' => 'Lo sentimos, "' . ($p['nombre'] ?? 'este producto') . '" está agotado.'];
    }
    $cantidad = max(1, $cantidad);
    $actual = (int)($_SESSION['carrito'][$productoId]['cantidad'] ?? 0);
    $nueva = $actual + $cantidad;
    $aviso = '';
    if ($nueva > $stock) {
        $nueva = $stock;
        $aviso = ' Solo hay ' . $stock . ' unidades disponibles; ajustamos la cantidad.';
    }
    $_SESSION['carrito'][$productoId] = [
        'nombre'   => (string)($p['nombre'] ?? ''),
        'precio'   => (float)($p['precio'] ?? 0),
        'imagen'   => (string)($p['imagen'] ?? ''),
        'cantidad' => $nueva,
    ];
    return ['ok' => true, 'mensaje' => '"' . $p['nombre'] . '" se agregó al carrito.' . $aviso, 'cantidad' => $nueva];
}

function carrito_actualizar(string $productoId, int $cantidad): array
{
    if (!isset($_SESSION['carrito'][$productoId])) {
        return ['ok' => false, 'mensaje' => 'El producto no está en tu carrito.'];
    }
    if ($cantidad <= 0) {
        carrito_quitar($productoId);
        return ['ok' => true, 'mensaje' => 'Producto retirado del carrito.'];
    }
    $p = producto_por_id($productoId);
    if (!$p) {
        carrito_quitar($productoId);
        return ['ok' => false, 'mensaje' => 'El producto ya no está disponible y se retiró del carrito.'];
    }
    $stock = (int)($p['stock'] ?? 0);
    $aviso = '';
    if ($cantidad > $stock) {
        $cantidad = $stock;
        $aviso = ' Solo hay ' . $stock . ' unidades disponibles.';
    }
    if ($cantidad <= 0) {
        carrito_quitar($productoId);
        return ['ok' => false, 'mensaje' => '"' . $p['nombre'] . '" se agotó y se retiró del carrito.'];
    }
    $_SESSION['carrito'][$productoId]['cantidad'] = $cantidad;
    $_SESSION['carrito'][$productoId]['precio'] = (float)($p['precio'] ?? 0);
    return ['ok' => true, 'mensaje' => 'Cantidad actualizada.' . $aviso];
}

function carrito_quitar(string $productoId): void
{
    unset($_SESSION['carrito'][$productoId]);
    if (empty($_SESSION['carrito'])) {
        unset($_SESSION['carrito']);
    }
}

function carrito_vaciar(): void
{
    unset($_SESSION['carrito']);
}

/**
 * Revisa el carrito contra MongoDB: precios actuales, stock y productos eliminados.
 * @return array{items:array,total:float,unidades:int,problemas:array}
 */
function carrito_resumen(): array
{
    $items = [];
    $problemas = [];
    $total = 0.0;
    $unidades = 0;
    $carrito = carrito_items();
    if (!$carrito) {
        return ['items' => [], 'total' => 0.0, 'unidades' => 0, 'problemas' => []];
    }
    $ids = array_values(array_filter(array_map('oid', array_map('strval', array_keys($carrito)))));
    $actuales = [];
    if ($ids) {
        foreach (mongo()->selectCollection('productos')->find(['_id' => ['$in' => $ids]]) as $p) {
            $actuales[(string)$p['_id']] = (array)$p;
        }
    }
    foreach ($carrito as $id => $item) {
        $id = (string)$id;
        $cantidad = max(1, (int)($item['cantidad'] ?? 1));
        $p = $actuales[$id] ?? null;
        if (!$p) {
            $problemas[] = ['id' => $id, 'tipo' => 'no_existe', 'mensaje' => '"' . ($item['nombre'] ?? 'Producto') . '" ya no está disponible.'];
            continue;
        }
        $stock  = (int)($p['stock'] ?? 0);
        $precio = (float)($p['precio'] ?? 0);
        if ($stock <= 0) {
            $problemas[] = ['id' => $id, 'tipo' => 'agotado', 'mensaje' => '"' . $p['nombre'] . '" está agotado.'];
        } elseif ($cantidad > $stock) {
            $problemas[] = ['id' => $id, 'tipo' => 'stock', 'mensaje' => 'Solo quedan ' . $stock . ' unidades de "' . $p['nombre'] . '".'];
        }
        if (abs($precio - (float)($item['precio'] ?? $precio)) > 0.004) {
            $problemas[] = ['id' => $id, 'tipo' => 'precio', 'mensaje' => 'El precio de "' . $p['nombre'] . '" cambió a ' . dinero($precio) . '.', 'leve' => true];
            $_SESSION['carrito'][$id]['precio'] = $precio;
        }
        $subtotal = $precio * $cantidad;
        $total += $subtotal;
        $unidades += $cantidad;
        $items[] = [
            'id' => $id, 'nombre' => (string)$p['nombre'], 'precio' => $precio, 'cantidad' => $cantidad,
            'subtotal' => $subtotal, 'stock' => $stock, 'imagen' => (string)($p['imagen'] ?? ''),
        ];
    }
    return ['items' => $items, 'total' => $total, 'unidades' => $unidades, 'problemas' => $problemas];
}

/** ¿Hay problemas que impidan finalizar la compra? (los cambios de precio solo informan) */
function carrito_bloqueado(array $resumen): bool
{
    foreach ($resumen['problemas'] as $p) {
        if (empty($p['leve'])) {
            return true;
        }
    }
    return empty($resumen['items']);
}
