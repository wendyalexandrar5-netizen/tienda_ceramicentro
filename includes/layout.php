<?php
/**
 * Plantilla visual compartida de CERAMISHOP (sitio público, tienda y panel admin).
 *
 * Uso en una página:
 *   layout_inicio(['titulo' => 'Productos', 'descripcion' => '...', 'canonical' => 'productos.php']);
 *   ... contenido ...
 *   layout_fin();
 */
require_once __DIR__ . '/bootstrap.php';

/** Etiquetas <head> comunes: SEO, Open Graph, favicon, estilos. */
function layout_head(array $m): void
{
    $tienda  = (string)config('empresa.tienda', 'CERAMISHOP');
    $empresa = (string)config('empresa.nombre', 'CERAMICENTRO');
    $titulo  = trim((string)($m['titulo'] ?? ''));
    $tituloCompleto = $titulo !== '' ? $titulo . ' | ' . $tienda . ' – ' . $empresa : $tienda . ' – ' . $empresa;
    $desc = (string)($m['descripcion'] ?? 'Tienda en línea de ' . $empresa . ': cerámica, baldosas, enchapes, techos PVC, baños y materiales de construcción.');
    $noindex = !empty($m['noindex']);
    $canonical = isset($m['canonical']) ? url_absoluta($m['canonical']) : null;
    $ogImg = $m['og_imagen'] ?? url_absoluta('imagenes/logosinfondo.png');
    ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($tituloCompleto) ?></title>
    <meta name="description" content="<?= e(resumen($desc, 160)) ?>">
    <meta name="robots" content="<?= $noindex ? 'noindex, nofollow' : 'index, follow' ?>">
    <?php if ($canonical && !$noindex): ?>
    <link rel="canonical" href="<?= e($canonical) ?>">
    <?php endif; ?>
    <?php if (!$noindex): ?>
    <meta property="og:site_name" content="<?= e($tienda . ' – ' . $empresa) ?>">
    <meta property="og:locale" content="es_CO">
    <meta property="og:type" content="<?= e($m['og_tipo'] ?? 'website') ?>">
    <meta property="og:title" content="<?= e($titulo !== '' ? $titulo : $tienda . ' – ' . $empresa) ?>">
    <meta property="og:description" content="<?= e(resumen($desc, 200)) ?>">
    <?php if ($canonical): ?><meta property="og:url" content="<?= e($canonical) ?>"><?php endif; ?>
    <meta property="og:image" content="<?= e($ogImg) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <?php endif; ?>
    <meta name="theme-color" content="#c62828">
    <link rel="icon" type="image/png" href="<?= e(url('imagenes/logosinfondo.png')) ?>">
    <link rel="apple-touch-icon" href="<?= e(url('imagenes/logosinfondo.png')) ?>">
    <?php if (!empty($m['precargar'])): ?><link rel="preload" as="image" href="<?= e($m['precargar']) ?>" fetchpriority="high"><?php endif; ?>
    <?php recursos_css(); ?>
    <link href="<?= e(asset('assets/css/ceramishop.css')) ?>" rel="stylesheet">
    <?php
    foreach ((array)($m['jsonld'] ?? []) as $bloque) {
        if ($bloque) {
            echo '<script type="application/ld+json">'
                . json_encode($bloque, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG)
                . "</script>\n";
        }
    }
}

/**
 * Bootstrap, íconos y fuente Poppins. Por defecto se sirven desde assets/vendor (funciona sin
 * internet, útil para demostraciones locales). Con 'recursos_cdn' => true se usan las CDN.
 */
function recursos_css(): void
{
    if (config('recursos_cdn')) {
        echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n"
            . '    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n"
            . '    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">' . "\n"
            . '    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">' . "\n"
            . '    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">' . "\n";
        return;
    }
    echo '<link rel="preload" href="' . e(url('assets/vendor/poppins/poppins-latin-400-normal.woff2')) . '" as="font" type="font/woff2" crossorigin>' . "\n"
        . '    <link href="' . e(url('assets/vendor/poppins/poppins.css')) . '" rel="stylesheet">' . "\n"
        . '    <link href="' . e(url('assets/vendor/bootstrap/bootstrap.min.css')) . '" rel="stylesheet">' . "\n"
        . '    <link href="' . e(url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css')) . '" rel="stylesheet">' . "\n";
}

function recursos_js(): void
{
    echo config('recursos_cdn')
        ? '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous" defer></script>'
        : '<script src="' . e(url('assets/vendor/bootstrap/bootstrap.bundle.min.js')) . '" defer></script>';
    echo "\n";
}

/** URL de Chart.js (local o CDN). */
function url_chartjs(): string
{
    return config('recursos_cdn') ? 'https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.js' : url('assets/vendor/chartjs/chart.umd.js');
}

/** Configuración que el JavaScript del sitio necesita (sin datos sensibles). */
function layout_config_js(): void
{
    $cfg = [
        'base'  => base_path(),
        'csrf'  => session_status() === PHP_SESSION_ACTIVE ? csrf_token() : '',
        'ga4'   => (string)config('analitica.ga4_id', ''),
        'debug' => !es_produccion(),
    ];
    echo '<script>window.CS_CONFIG=' . json_encode($cfg, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . ';</script>' . "\n";
}

function layout_flash(): void
{
    $mensajes = flash_obtener();
    if (!empty($_SESSION['sesion_expirada'])) {
        unset($_SESSION['sesion_expirada']);
        array_unshift($mensajes, ['tipo' => 'warning', 'mensaje' => 'Tu sesión expiró por inactividad. Vuelve a iniciar sesión.']);
    }
    if (!$mensajes) {
        return;
    }
    $iconos = ['success' => 'check-circle-fill', 'error' => 'exclamation-octagon-fill', 'warning' => 'exclamation-triangle-fill', 'info' => 'info-circle-fill'];
    echo '<div class="flash-zona container">';
    foreach ($mensajes as $m) {
        $tipo = $m['tipo'] === 'error' ? 'danger' : $m['tipo'];
        $rol = in_array($m['tipo'], ['error', 'warning'], true) ? 'alert' : 'status';
        echo '<div class="alert alert-' . e($tipo) . ' alert-dismissible fade show d-flex align-items-start gap-2" role="' . $rol . '">'
            . '<i class="bi bi-' . e($iconos[$m['tipo']] ?? 'info-circle-fill') . ' flex-shrink-0" aria-hidden="true"></i>'
            . '<div>' . e($m['mensaje']) . '</div>'
            . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar mensaje"></button></div>';
    }
    echo '</div>';
}

/** Datos estructurados de la organización (solo con información existente en el proyecto). */
function jsonld_organizacion(): array
{
    $org = [
        '@context' => 'https://schema.org',
        '@type'    => 'Organization',
        '@id'      => url_absoluta('#organizacion'),
        'name'     => (string)config('empresa.nombre', 'CERAMICENTRO'),
        'url'      => url_absoluta(),
        'logo'     => url_absoluta('imagenes/logosinfondo.png'),
        'description' => 'Empresa dedicada a productos de cerámica, baldosas, techos PVC y materiales de construcción.',
    ];
    $sameAs = array_values(array_filter([config('empresa.facebook'), config('empresa.instagram')]));
    if ($sameAs) {
        $org['sameAs'] = $sameAs;
    }
    $wa = preg_replace('/\D/', '', (string)config('empresa.whatsapp', ''));
    $tel = (string)config('empresa.telefono', '');
    if ($tel !== '' || $wa !== '') {
        $org['contactPoint'] = [
            '@type' => 'ContactPoint',
            'telephone' => $tel !== '' ? $tel : '+' . $wa,
            'contactType' => 'customer service',
            'availableLanguage' => 'es',
        ];
    }
    if (config('empresa.correo')) {
        $org['email'] = (string)config('empresa.correo');
    }
    return $org;
}

function jsonld_sitio_web(): array
{
    return [
        '@context' => 'https://schema.org',
        '@type'    => 'WebSite',
        'name'     => (string)config('empresa.tienda', 'CERAMISHOP') . ' – ' . (string)config('empresa.nombre', 'CERAMICENTRO'),
        'url'      => url_absoluta(),
        'inLanguage' => 'es-CO',
        'publisher' => ['@id' => url_absoluta('#organizacion')],
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => url_absoluta('productos.php') . '?q={search_term_string}',
            'query-input' => 'required name=search_term_string',
        ],
    ];
}

/** @param array $migas [['nombre' => 'Inicio', 'url' => 'index.php'], ...] (el último puede no tener url) */
function jsonld_migas(array $migas): array
{
    $items = [];
    foreach ($migas as $i => $m) {
        $item = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $m['nombre']];
        if (!empty($m['url'])) {
            $item['item'] = url_absoluta($m['url']);
        }
        $items[] = $item;
    }
    return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items];
}

function migas_html(array $migas): void
{
    echo '<nav aria-label="Ruta de navegación" class="migas"><ol class="breadcrumb mb-0">';
    $ultimo = count($migas) - 1;
    foreach ($migas as $i => $m) {
        if ($i === $ultimo || empty($m['url'])) {
            echo '<li class="breadcrumb-item active" aria-current="page">' . e($m['nombre']) . '</li>';
        } else {
            echo '<li class="breadcrumb-item"><a href="' . e(url($m['url'])) . '">' . e($m['nombre']) . '</a></li>';
        }
    }
    echo '</ol></nav>';
}

/** Enlace de WhatsApp con mensaje opcional. */
function whatsapp_url(string $mensaje = ''): string
{
    $num = preg_replace('/\D/', '', (string)config('empresa.whatsapp', ''));
    if ($num === '') {
        return '';
    }
    return 'https://wa.me/' . $num . ($mensaje !== '' ? '?text=' . rawurlencode($mensaje) : '');
}

/**
 * Inicio de página del sitio público / tienda de clientes.
 * @param array $m titulo, descripcion, canonical, og_imagen, og_tipo, noindex, jsonld, activo, body_class, migas
 */
function layout_inicio(array $m = []): void
{
    $activo = $m['activo'] ?? '';
    $u = usuario_actual();
    $nCarrito = es_cliente() ? carrito_contar_seguro() : 0;
    $nav = [
        'inicio'    => ['index.php', 'Inicio'],
        'nosotros'  => ['nosotros.php', 'Nosotros'],
        'productos' => ['productos.php', 'Productos'],
        'contacto'  => ['contacto.php', 'Contáctanos'],
    ];
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php layout_head($m); ?>
</head>
<body class="<?= e($m['body_class'] ?? '') ?>">
<a class="saltar-contenido" href="#contenido">Saltar al contenido</a>
<header class="cs-header">
    <nav class="navbar navbar-expand-lg navbar-dark" aria-label="Navegación principal">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="<?= e(url(es_cliente() ? 'tienda.php' : 'index.php')) ?>">
                <img src="<?= e(url('imagenes/logosinfondo.png')) ?>" alt="" width="44" height="44" class="cs-logo" onerror="this.style.display='none'">
                <span class="cs-marca">CERAMICENTRO</span>
            </a>
            <div class="d-flex align-items-center gap-2 order-lg-last">
                <?php if (es_cliente()): ?>
                <a href="<?= e(url('ver_carrito.php')) ?>" class="btn btn-carrito position-relative" aria-label="Carrito de compras, <?= $nCarrito ?> productos">
                    <i class="bi bi-cart3" aria-hidden="true"></i>
                    <span class="badge rounded-pill cs-badge-carrito" data-carrito-contador <?= $nCarrito ? '' : 'hidden' ?>><?= $nCarrito ?></span>
                </a>
                <?php endif; ?>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuPrincipal" aria-controls="menuPrincipal" aria-expanded="false" aria-label="Abrir menú">
                    <span class="navbar-toggler-icon"></span>
                </button>
            </div>
            <div class="collapse navbar-collapse" id="menuPrincipal">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                    <?php foreach ($nav as $clave => [$href, $texto]): ?>
                    <li class="nav-item"><a class="nav-link<?= $activo === $clave ? ' active' : '' ?>" <?= $activo === $clave ? 'aria-current="page"' : '' ?> href="<?= e(url($href)) ?>"><?= e($texto) ?></a></li>
                    <?php endforeach; ?>
                    <?php if (es_cliente()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle<?= in_array($activo, ['tienda', 'pedidos', 'cuenta'], true) ? ' active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle" aria-hidden="true"></i> <?= e(explode(' ', trim($u['nombre'] ?? 'Mi cuenta'))[0] ?: 'Mi cuenta') ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?= e(url('tienda.php')) ?>"><i class="bi bi-shop" aria-hidden="true"></i> Tienda</a></li>
                            <li><a class="dropdown-item" href="<?= e(url('ver_carrito.php')) ?>"><i class="bi bi-cart3" aria-hidden="true"></i> Mi carrito</a></li>
                            <li><a class="dropdown-item" href="<?= e(url('mis_pedidos.php')) ?>"><i class="bi bi-box-seam" aria-hidden="true"></i> Mis pedidos</a></li>
                            <li><a class="dropdown-item" href="<?= e(url('mi_cuenta.php')) ?>"><i class="bi bi-person-gear" aria-hidden="true"></i> Mi cuenta</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= e(url('logout.php')) ?>"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> Cerrar sesión</a></li>
                        </ul>
                    </li>
                    <?php elseif (es_admin()): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('panel_admin.php')) ?>"><i class="bi bi-speedometer2" aria-hidden="true"></i> Panel admin</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('logout.php')) ?>">Cerrar sesión</a></li>
                    <?php else: ?>
                    <li class="nav-item"><a class="nav-link<?= $activo === 'login' ? ' active' : '' ?>" href="<?= e(url('login.php')) ?>">Iniciar sesión</a></li>
                    <li class="nav-item"><a class="btn btn-light btn-sm ms-lg-2 fw-semibold text-danger-emphasis" href="<?= e(url('registro.php')) ?>">Crear cuenta</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
</header>
<main id="contenido" tabindex="-1">
<?php
    layout_flash();
    if (!empty($m['migas'])) {
        echo '<div class="container pt-3">';
        migas_html($m['migas']);
        echo '</div>';
    }
}

function carrito_contar_seguro(): int
{
    $n = 0;
    foreach ((array)($_SESSION['carrito'] ?? []) as $item) {
        $n += (int)($item['cantidad'] ?? 0);
    }
    return $n;
}

function layout_fin(array $opc = []): void
{
    $wa = whatsapp_url('Hola CERAMICENTRO, quiero información sobre sus productos.');
    $anio = date('Y');
    ?>
</main>
<footer class="cs-footer mt-5">
    <div class="container py-5">
        <div class="row g-4">
            <div class="col-md-5">
                <p class="h5 text-white fw-bold mb-2">CERAMICENTRO</p>
                <p class="mb-0 small">Calidad, diseño e innovación en cerámica, baldosas, techos PVC y materiales para tus espacios.</p>
            </div>
            <div class="col-6 col-md-2">
                <p class="cs-footer-titulo">Tienda</p>
                <ul class="list-unstyled small mb-0">
                    <li><a href="<?= e(url('index.php')) ?>">Inicio</a></li>
                    <li><a href="<?= e(url('productos.php')) ?>">Productos</a></li>
                    <li><a href="<?= e(url('nosotros.php')) ?>">Nosotros</a></li>
                    <li><a href="<?= e(url('contacto.php')) ?>">Contáctanos</a></li>
                </ul>
            </div>
            <div class="col-6 col-md-3">
                <p class="cs-footer-titulo">Legal</p>
                <ul class="list-unstyled small mb-0">
                    <li><a href="<?= e(url('tyc.php')) ?>">Términos y condiciones</a></li>
                    <li><a href="<?= e(url('aviso_legal.php')) ?>">Aviso legal</a></li>
                    <li><a href="<?= e(url('politica_privacidad.php')) ?>">Política de privacidad</a></li>
                    <li><a href="<?= e(url('politica_cookies.php')) ?>">Política de cookies</a></li>
                    <li><button type="button" class="btn btn-link p-0 small cs-footer-link" data-cookies-configurar>Configurar cookies</button></li>
                </ul>
            </div>
            <div class="col-md-2">
                <p class="cs-footer-titulo">Contacto</p>
                <ul class="list-unstyled small mb-0">
                    <?php if ($wa): ?><li><a href="<?= e($wa) ?>" target="_blank" rel="noopener" data-evento="contacto_whatsapp"><i class="bi bi-whatsapp" aria-hidden="true"></i> WhatsApp</a></li><?php endif; ?>
                    <li><a href="<?= e(url('contacto.php')) ?>"><i class="bi bi-envelope" aria-hidden="true"></i> Escríbenos</a></li>
                </ul>
            </div>
        </div>
    </div>
<?php if ($wa && empty($opc['sin_whatsapp'])): ?>
<a href="<?= e($wa) ?>" class="cs-whatsapp" target="_blank" rel="noopener" aria-label="Escríbenos por WhatsApp" data-evento="contacto_whatsapp">
    <i class="bi bi-whatsapp" aria-hidden="true"></i><span class="cs-whatsapp-texto">¿Te ayudamos?</span>
</a>
<?php endif; ?>

<div class="cs-cookies" id="avisoCookies" role="dialog" aria-live="polite" aria-label="Aviso de cookies" hidden>
    <div class="container d-flex flex-column flex-md-row align-items-md-center gap-3">
        <p class="mb-0 small flex-grow-1">
            Usamos cookies necesarias para que la tienda funcione (sesión y carrito). Con tu permiso, también usamos cookies de analítica
            para entender cómo se usa el sitio, sin recopilar datos personales. <a href="<?= e(url('politica_cookies.php')) ?>">Leer la política de cookies</a>.
        </p>
        <div class="d-flex gap-2 flex-shrink-0">
            <button type="button" class="btn btn-outline-light btn-sm" data-cookies="necesarias">Solo necesarias</button>
            <button type="button" class="btn btn-light btn-sm fw-semibold" data-cookies="todas">Aceptar todas</button>
        </div>
    </div>
</div>
    <div class="cs-footer-base">
        <div class="container py-3 small text-center">
            © <?= e($anio) ?> <strong>CERAMISHOP</strong> - Todos los derechos reservados
            <?php if (config('academico.activo')): ?>
            <br><span class="opacity-75"><?= e(texto_academico_corto()) ?> <a href="<?= e(url('aviso_legal.php')) ?>">Más información en el aviso legal</a></span>
            <?php endif; ?>
        </div>
    </div>
</footer>

<?php layout_config_js(); ?>
<?php recursos_js(); ?>
<script src="<?= e(asset('assets/js/ceramishop.js')) ?>" defer></script>
<?php foreach ((array)($opc['scripts'] ?? []) as $s): ?>
<script src="<?= e($s) ?>" defer></script>
<?php endforeach; ?>
<?php if (!empty($opc['eventos'])): ?>
<script>window.CS_EVENTOS=<?= json_encode($opc['eventos'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
<?php endif; ?>
</body>
</html>
<?php
}

/* ================================================================
 * PANEL ADMINISTRATIVO
 * ================================================================ */

function admin_menu(): array
{
    return [
        'dashboard'   => ['panel_admin.php', 'speedometer2', 'Dashboard'],
        'productos'   => ['ver_productos.php', 'box-seam', 'Productos'],
        'agregar'     => ['agregar_producto.php', 'plus-circle', 'Añadir / Categorías'],
        'inventario'  => ['inventario.php', 'clipboard-data', 'Inventario'],
        'pedidos'     => ['historial_pedidos_admin.php', 'receipt', 'Pedidos'],
        'estadisticas'=> ['estadisticas_ventas.php', 'graph-up', 'Estadísticas de ventas'],
        'historial'   => ['historial_productos.php', 'clock-history', 'Historial de productos'],
        'usuarios'    => ['ver_usuarios.php', 'people', 'Usuarios'],
        'admins'      => ['crear_admin.php', 'person-gear', 'Añadir administrador'],
        'mensajes'    => ['mensajes_contacto.php', 'envelope', 'Mensajes de contacto'],
    ];
}

function admin_inicio(string $titulo, string $activo = '', array $opc = []): void
{
    $u = usuario_actual();
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php layout_head(['titulo' => $titulo . ' · Administración', 'noindex' => true]); ?>
</head>
<body class="admin-body">
<a class="saltar-contenido" href="#contenido">Saltar al contenido</a>
<header class="admin-topbar navbar navbar-dark">
    <div class="container-fluid">
        <button class="btn btn-outline-light d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminMenu" aria-controls="adminMenu" aria-label="Abrir menú de administración">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>
        <a class="navbar-brand d-flex align-items-center gap-2 me-auto" href="<?= e(url('panel_admin.php')) ?>">
            <img src="<?= e(url('imagenes/logosinfondo.png')) ?>" alt="" width="34" height="34" class="cs-logo" onerror="this.style.display='none'">
            <span class="fw-bold">CERAMISHOP <span class="fw-normal d-none d-sm-inline">· Administración</span></span>
        </a>
        <span class="text-white-50 small d-none d-md-inline me-3"><i class="bi bi-person-badge" aria-hidden="true"></i> <?= e($u['nombre'] ?? '') ?></span>
        <a class="btn btn-sm btn-light" href="<?= e(url('logout.php')) ?>"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> <span class="d-none d-sm-inline">Salir</span></a>
    </div>
</header>
<div class="admin-layout">
    <nav class="offcanvas-lg offcanvas-start admin-sidebar" tabindex="-1" id="adminMenu" aria-label="Menú de administración">
        <div class="offcanvas-header d-lg-none">
            <span class="offcanvas-title fw-bold">Menú</span>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#adminMenu" aria-label="Cerrar menú"></button>
        </div>
        <div class="offcanvas-body p-0">
            <ul class="nav flex-column w-100 py-2">
                <?php foreach (admin_menu() as $clave => [$href, $icono, $texto]): ?>
                <li class="nav-item">
                    <a class="nav-link<?= $activo === $clave ? ' active' : '' ?>" <?= $activo === $clave ? 'aria-current="page"' : '' ?> href="<?= e(url($href)) ?>">
                        <i class="bi bi-<?= e($icono) ?>" aria-hidden="true"></i> <?= e($texto) ?>
                    </a>
                </li>
                <?php endforeach; ?>
                <li class="nav-item mt-2 border-top pt-2">
                    <a class="nav-link" href="<?= e(url('index.php')) ?>" target="_blank" rel="noopener"><i class="bi bi-shop-window" aria-hidden="true"></i> Ver sitio público</a>
                </li>
            </ul>
        </div>
    </nav>
    <main id="contenido" class="admin-main" tabindex="-1">
        <div class="admin-encabezado d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
            <h1 class="h3 mb-0"><?= e($titulo) ?></h1>
            <?php if (!empty($opc['acciones'])): ?><div class="d-flex flex-wrap gap-2"><?= $opc['acciones'] ?></div><?php endif; ?>
        </div>
        <?php layout_flash(); ?>
<?php
}

function admin_fin(array $opc = []): void
{
    ?>
        <footer class="text-center text-muted small mt-5 pb-3">© <?= e(date('Y')) ?> <strong>CERAMISHOP</strong> - Todos los derechos reservados</footer>
    </main>
</div>
<?php layout_config_js(); ?>
<?php recursos_js(); ?>
<script src="<?= e(asset('assets/js/ceramishop.js')) ?>" defer></script>
<?php foreach ((array)($opc['scripts'] ?? []) as $s): ?>
<script src="<?= e($s) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
<?php
}

/** Frase breve del aviso de proyecto académico. */
function texto_academico_corto(): string
{
    $inst = trim((string)config('academico.institucion', ''));
    return 'Proyecto académico' . ($inst !== '' ? ' de ' . $inst : ' universitario') . ': no se realizan ventas, envíos ni cobros reales.';
}

/** Paginación accesible (conserva los filtros actuales). */
function paginacion_html(int $pagina, int $paginas, string $ruta, array $params = []): void
{
    if ($paginas <= 1) {
        return;
    }
    echo '<nav aria-label="Paginación" class="mt-4"><ul class="pagination justify-content-center flex-wrap">';
    $link = function (int $p, string $texto, bool $activo = false, bool $deshabilitado = false, string $aria = '') use ($ruta, $params) {
        $cls = 'page-item' . ($activo ? ' active' : '') . ($deshabilitado ? ' disabled' : '');
        $attrs = $activo ? ' aria-current="page"' : '';
        $attrs .= $aria ? ' aria-label="' . e($aria) . '"' : '';
        if ($deshabilitado) {
            return '<li class="' . $cls . '"><span class="page-link"' . $attrs . '>' . $texto . '</span></li>';
        }
        return '<li class="' . $cls . '"><a class="page-link" href="' . e(url($ruta, array_merge($params, ['pagina' => $p]))) . '"' . $attrs . '>' . $texto . '</a></li>';
    };
    echo $link(max(1, $pagina - 1), '&laquo;', false, $pagina <= 1, 'Página anterior');
    $desde = max(1, $pagina - 2);
    $hasta = min($paginas, $pagina + 2);
    if ($desde > 1) {
        echo $link(1, '1');
        if ($desde > 2) {
            echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
    }
    for ($p = $desde; $p <= $hasta; $p++) {
        echo $link($p, (string)$p, $p === $pagina);
    }
    if ($hasta < $paginas) {
        if ($hasta < $paginas - 1) {
            echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
        echo $link($paginas, (string)$paginas);
    }
    echo $link(min($paginas, $pagina + 1), '&raquo;', false, $pagina >= $paginas, 'Página siguiente');
    echo '</ul></nav>';
}

/** Badge de estado de pedido. */
function estado_badge(string $estado): string
{
    $clase = function_exists('estado_clase') ? estado_clase($estado) : 'estado-neutro';
    return '<span class="estado-badge ' . e($clase) . '">' . e($estado) . '</span>';
}
