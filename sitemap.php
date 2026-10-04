<?php
/** sitemap.xml dinámico: solo URLs públicas (inicio, páginas informativas, categorías y productos). */
define('CS_SIN_SESION', true);
require_once __DIR__ . '/includes/tienda.php';

$urls = [];
$ahora = date('Y-m-d');
foreach ([['index.php', '1.0', 'weekly'], ['productos.php', '0.9', 'daily'], ['nosotros.php', '0.6', 'monthly'], ['contacto.php', '0.6', 'monthly'],
          ['tyc.php', '0.2', 'yearly'], ['aviso_legal.php', '0.2', 'yearly'], ['politica_privacidad.php', '0.2', 'yearly'], ['politica_cookies.php', '0.2', 'yearly']] as [$r, $pr, $fr]) {
    $urls[] = ['loc' => url_absoluta($r), 'priority' => $pr, 'changefreq' => $fr];
}
try {
    foreach (categorias_todas() as $c) {
        $urls[] = ['loc' => url_absoluta('productos.php', ['categoria' => $c['id']]), 'priority' => '0.8', 'changefreq' => 'weekly'];
    }
    foreach (mongo()->selectCollection('productos')->find([], ['projection' => ['_id' => 1, 'actualizado_en' => 1, 'createdAt' => 1], 'sort' => ['_id' => 1]]) as $p) {
        $f = $p['actualizado_en'] ?? ($p['createdAt'] ?? null);
        $urls[] = [
            'loc' => url_absoluta('producto.php', ['id' => (string)$p['_id']]),
            'lastmod' => $f instanceof \MongoDB\BSON\UTCDateTime ? $f->toDateTime()->format('Y-m-d') : null,
            'priority' => '0.7', 'changefreq' => 'weekly',
        ];
    }
} catch (Throwable $e) {
    log_app('aviso', 'Sitemap sin productos: ' . $e->getMessage());
}

header('Content-Type: application/xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo "  <url>\n    <loc>" . htmlspecialchars($u['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n";
    if (!empty($u['lastmod'])) {
        echo '    <lastmod>' . $u['lastmod'] . "</lastmod>\n";
    }
    echo '    <changefreq>' . $u['changefreq'] . "</changefreq>\n    <priority>" . $u['priority'] . "</priority>\n  </url>\n";
}
echo "</urlset>\n";
