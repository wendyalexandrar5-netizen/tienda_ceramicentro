<?php
/**
 * Router para el servidor embebido de PHP que imita las reglas del .htaccess de Apache
 * (solo para pruebas locales:  php -S 127.0.0.1:8080 tests/router.php).
 * Soporta instalación en subcarpeta mediante la variable CS_BASE (ej. /tiendaonline_mongodb).
 */
require_once __DIR__ . '/fake_mongo.php';
$base = rtrim((string)getenv('CS_BASE'), '/');
$raiz = dirname(__DIR__);
$ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$ruta = rawurldecode($ruta);
if ($base !== '' && strpos($ruta, $base) !== 0) {
    http_response_code(404);
    echo 'Fuera de la carpeta de la tienda';
    return true;
}
$rel = ltrim(substr($ruta, strlen($base)), '/');

$ejecutar = function (string $archivo) use ($raiz, $base) {
    $_SERVER['SCRIPT_FILENAME'] = $raiz . '/' . $archivo;
    $_SERVER['SCRIPT_NAME'] = $base . '/' . $archivo;
    $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
    chdir(dirname($raiz . '/' . $archivo));
    require $raiz . '/' . $archivo;
    return true;
};

if (preg_match('#^(index|nosotros|productos|tyc)\.html$#', $rel, $m)) {
    header('Location: ' . $base . '/' . $m[1] . '.php' . (($q = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY)) ? '?' . $q : ''), true, 301);
    return true;
}
if ($rel === 'sitemap.xml') return $ejecutar('sitemap.php');
if ($rel === 'robots.txt') return $ejecutar('robots.php');
if (preg_match('#^(vendor|config|includes|logs|scripts|tests|mobile-app)(/|$)|^(composer\.(json|lock)|README\.md)$|^api/_#', $rel)) {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}
if ($rel === '') $rel = 'index.php';
$archivo = $raiz . '/' . $rel;
if (is_dir($archivo) && is_file($archivo . '/index.php')) $rel .= '/index.php';
if (is_file($raiz . '/' . $rel)) {
    if (substr($rel, -4) === '.php') return $ejecutar($rel);
    $tipos = ['css' => 'text/css', 'js' => 'application/javascript', 'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'avif' => 'image/avif', 'txt' => 'text/plain', 'html' => 'text/html'];
    $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
    header('Content-Type: ' . ($tipos[$ext] ?? 'application/octet-stream'));
    readfile($raiz . '/' . $rel);
    return true;
}
return $ejecutar('404.php');
