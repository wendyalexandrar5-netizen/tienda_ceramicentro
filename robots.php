<?php
/** robots.txt dinámico (incluye la URL absoluta del sitemap). */
define('CS_SIN_SESION', true);
require_once __DIR__ . '/includes/bootstrap.php';
header('Content-Type: text/plain; charset=UTF-8');
$b = base_path();
$privadas = [
    'panel_admin.php', 'agregar_producto.php', 'editar_producto.php', 'eliminar_producto.php', 'ver_productos.php', 'inventario.php',
    'categoria_editar.php', 'categoria_eliminar.php', 'crear_admin.php', 'ver_usuarios.php', 'historial_productos.php',
    'historial_pedidos_admin.php', 'estadisticas_ventas.php', 'mensajes_contacto.php', 'test_mongo.php', 'admin_pedido_pdf.php',
    'reporte_ventas_pdf.php', 'exportar_', 'tienda.php', 'ver_carrito.php', 'agregar_carrito.php', 'actualizar_carrito.php',
    'eliminar_del_carrito.php', 'vaciar_carrito.php', 'realizar_pedido.php', 'pago_pse.php', 'pago_exitoso.php', 'mis_pedidos.php',
    'ver_pedido.php', 'descargar_pedido.php', 'generar_comprobante.php', 'repetir_pedido.php', 'mi_cuenta.php', 'logout.php',
    'buscar_producto_tienda.php', 'acceso_denegado.php', 'api/', 'includes/', 'config/', 'vendor/', 'logs/', 'scripts/', 'mobile-app/',
];
echo "User-agent: *\n";
foreach ($privadas as $p) {
    echo 'Disallow: ' . $b . '/' . $p . "\n";
}
echo 'Allow: ' . $b . "/\n\n";
echo 'Sitemap: ' . url_absoluta('sitemap.xml') . "\n";
