// Prueba E2E de CERAMISHOP con Playwright (CDN servidas desde copias locales)
const { chromium } = require('/opt/node22/lib/node_modules/playwright');
const fs = require('fs');
const path = require('path');
const B = 'http://127.0.0.1:8080/tiendaonline_mongodb';
const ids = JSON.parse(fs.readFileSync('/tmp/cs_ids.json'));
const SP = process.env.CS_NPM_LOCAL || '';
const cdn = {
  'bootstrap@5.3.3/dist/css/bootstrap.min.css': SP + '/bootstrap-5.3.3/package/dist/css/bootstrap.min.css',
  'bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js': SP + '/bootstrap-5.3.3/package/dist/js/bootstrap.bundle.min.js',
  'bootstrap-icons@1.11.3/font/bootstrap-icons.min.css': SP + '/bootstrap-icons-1.11.3/package/font/bootstrap-icons.min.css',
  'chart.js@4.4.6/dist/chart.umd.js': SP + '/chart.js-4.4.6/package/dist/chart.umd.js',
};
const problemas = [];
let pasoActual = '';
function ok(cond, msg) { if (!cond) { problemas.push('[' + pasoActual + '] FALLA: ' + msg); console.log('  ✘ ' + msg); } else console.log('  ✔ ' + msg); }

async function nuevaPagina(ctx) {
  const page = await ctx.newPage();
  page.on('console', m => { if (['error', 'warning'].includes(m.type())) problemas.push('[' + pasoActual + '] consola ' + m.type() + ': ' + m.text()); });
  page.on('pageerror', e => problemas.push('[' + pasoActual + '] JS error: ' + e.message));
  page.on('response', r => { const u = r.url(); if (r.status() >= 400 && u.startsWith(B) && !/imagenes\/|img\/|noexiste|000000000000|acceso_denegado|xyz/.test(u)) problemas.push('[' + pasoActual + '] HTTP ' + r.status() + ' ' + u); });
  return page;
}
async function rutas(ctx) {
  await ctx.route(/cdn\.jsdelivr\.net\/npm\/(.+?)(\?.*)?$/, (route, req) => {
    const m = req.url().match(/npm\/([^?]+)/)[1];
    if (m.startsWith('bootstrap-icons@1.11.3/font/fonts/')) {
      return route.fulfill({ path: SP + '/bootstrap-icons-1.11.3/package/font/fonts/' + path.basename(m) });
    }
    if (cdn[m]) return route.fulfill({ path: cdn[m], contentType: m.endsWith('.css') ? 'text/css' : 'application/javascript' });
    return route.fulfill({ status: 404, body: '' });
  });
  await ctx.route(/fonts\.(googleapis|gstatic)\.com/, r => r.fulfill({ status: 200, contentType: 'text/css', body: '' }));
  await ctx.route(/google\.com\/maps/, r => r.fulfill({ status: 200, contentType: 'text/html', body: '<html><body>mapa</body></html>' }));
}
async function sinScrollHorizontal(page, etiqueta) {
  const w = await page.evaluate(() => [document.documentElement.scrollWidth, window.innerWidth]);
  ok(w[0] <= w[1] + 1, 'sin scroll horizontal en ' + etiqueta + ' (' + w[0] + ' <= ' + w[1] + ')');
}

(async () => {
  const browser = await chromium.launch();
  // ===== Visitante =====
  let ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  await rutas(ctx);
  let page = await nuevaPagina(ctx);
  pasoActual = 'visitante';
  console.log('Visitante');
  await page.goto(B + '/');
  ok(await page.locator('h1').first().innerText() === 'CERAMICENTRO' || /ceramicentro/i.test(await page.locator('h1').first().innerText()), 'H1 de inicio');
  ok(await page.locator('#avisoCookies').isVisible(), 'aviso de cookies visible');
  await page.click('[data-cookies="necesarias"]');
  ok(!(await page.locator('#avisoCookies').isVisible()), 'aviso de cookies se oculta');
  ok(await page.locator('a.cs-whatsapp').getAttribute('href') === 'https://wa.me/573134322830?text=Hola%20CERAMICENTRO%2C%20quiero%20informaci%C3%B3n%20sobre%20sus%20productos.', 'botón WhatsApp con número configurado');
  await page.goto(B + '/productos.php');
  ok(await page.locator('.producto-card').count() > 5, 'catálogo público muestra productos');
  await page.fill('#filtroQ', 'techo');
  await Promise.all([page.waitForNavigation(), page.click('form[role=search] button[type=submit]')]);
  ok((await page.locator('.producto-card').count()) === 2, 'búsqueda "techo" devuelve 2');
  await page.goto(B + '/producto.php?id=' + ids.producto_castillo);
  ok(await page.locator('text=Inicia sesión para comprar').count() === 1, 'detalle invita a iniciar sesión');
  await page.goto(B + '/noexiste/ruta.php');
  ok(await page.locator('.error-codigo').innerText() === '404', 'página 404 personalizada');
  ok(await page.locator('a:has-text("Volver al inicio")').count() > 0, '404 con botón a inicio');
  const r403 = await page.goto(B + '/historial_pedidos_admin.php');
  ok(page.url().includes('login.php'), 'ruta admin redirige a login');
  // Registro con validación
  pasoActual = 'registro';
  console.log('Registro');
  await page.goto(B + '/registro.php');
  await page.click('button[type=submit]');
  ok(await page.locator('.is-invalid').count() >= 3, 'validación del registro en cliente');
  await page.fill('#nombre', 'Cliente Prueba');
  await page.fill('#correo', 'nuevo@correo.test');
  await page.fill('#clave', 'corta');
  await page.fill('#confirmar', 'corta');
  await page.check('#acepto');
  await page.click('button[type=submit]');
  ok(await page.locator('#clave.is-invalid').count() === 1, 'contraseña débil rechazada');
  await page.fill('#clave', 'Segura2026');
  await page.fill('#confirmar', 'Segura2026');
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  ok(page.url().includes('tienda.php'), 'registro inicia sesión y lleva a la tienda');
  await page.goto(B + '/logout.php');
  ok(page.url().includes('login.php'), 'logout');
  await ctx.close();

  // ===== Cliente (cuenta antigua con correo en mayúsculas) =====
  ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  await rutas(ctx);
  page = await nuevaPagina(ctx);
  pasoActual = 'login cliente';
  console.log('Cliente');
  await page.goto(B + '/login.php');
  await page.fill('#correo', 'laura@correo.test');
  await page.fill('#clave', 'malaclave1');
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  ok(await page.locator('.alert-danger').innerText().then(t => t.includes('Correo o contraseña incorrectos')), 'error genérico de login');
  await page.fill('#clave', 'Cliente123');
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  ok(page.url().includes('tienda.php'), 'login cliente antiguo (campo contraseña, correo con mayúsculas)');
  pasoActual = 'carrito';
  // Agregar al carrito con AJAX
  const card = page.locator('.producto-card', { hasText: 'Baldosa azulejo en tonos esmeralda' });
  await card.locator('input[name=cantidad]').fill('2');
  await card.locator('button[type=submit]').click();
  await page.waitForSelector('.toast.show');
  ok((await page.locator('[data-carrito-contador]').first().innerText()) === '2', 'contador del carrito se actualiza (AJAX)');
  const card2 = page.locator('.producto-card', { hasText: 'Enchape castillo' });
  await card2.locator('button[type=submit]').click();
  await page.waitForFunction(() => document.querySelector('[data-carrito-contador]').textContent === '3');
  // Producto agotado no tiene botón
  ok(await page.locator('.producto-card', { hasText: 'hexagonal' }).locator('button').count() === 0, 'producto agotado sin botón agregar');
  await page.goto(B + '/ver_carrito.php');
  ok(await page.locator('.carrito-item').count() === 2, 'carrito con 2 productos');
  // Cantidad mayor al stock se ajusta
  const item = page.locator('.carrito-item', { hasText: 'esmeralda' });
  await item.locator('input[name=cantidad]').evaluate(e => { e.removeAttribute('max'); e.value = '50'; });
  await Promise.all([page.waitForNavigation(), item.locator('form').first().locator('button').click()]);
  ok(await page.locator('.alert', { hasText: 'Solo hay 3' }).count() === 1, 'cantidad ajustada al stock');
  pasoActual = 'checkout';
  await Promise.all([page.waitForNavigation(), page.click('a:has-text("Finalizar compra")')]);
  ok(page.url().includes('realizar_pedido.php'), 'paso confirmar pedido');
  const totalTxt = await page.locator('.resumen-compra .total').innerText();
  ok(totalTxt.replace(/\D/g, '') === String(3 * 61000 + 58000), 'total correcto en confirmación: ' + totalTxt);
  await Promise.all([page.waitForNavigation(), page.click('button:has-text("Confirmar y pagar")')]);
  ok(page.url().includes('pago_pse.php'), 'llega al simulador PSE');
  ok(await page.locator('.pse-aviso').innerText().then(t => /no realiza cobros reales/i.test(t)), 'simulador se identifica como simulación');
  // Pago rechazado
  await page.selectOption('#banco', { index: 1 });
  await page.check('#rNo');
  await Promise.all([page.waitForNavigation({ timeout: 10000 }), page.click('#formPse button[type=submit]')]);
  ok(await page.locator('h2:has-text("Pago rechazado")').count() === 1, 'pago rechazado mostrado');
  // Reintentar y aprobar
  await Promise.all([page.waitForNavigation(), page.click('button:has-text("Intentar de nuevo")')]);
  await page.selectOption('#banco', { index: 2 });
  await Promise.all([page.waitForNavigation({ timeout: 10000 }), page.click('#formPse button[type=submit]')]);
  ok(page.url().includes('pago_exitoso.php'), 'pago aprobado → confirmación');
  const numero = await page.locator('dd.fw-bold').first().innerText();
  ok(/^CS-\d{6}$/.test(numero), 'número de pedido legible: ' + numero);
  ok(await page.locator('.estado-badge', { hasText: 'Pagado' }).count() === 1, 'estado Pagado');
  // Comprobante PDF
  const pdfResp = await page.request.get(B + '/generar_comprobante.php?id=' + new URL(page.url()).searchParams.get('id'));
  ok(pdfResp.headers()['content-type'].includes('application/pdf') && (await pdfResp.body()).slice(0, 4).toString() === '%PDF', 'comprobante PDF generado');
  // Seguridad: pedido de otro cliente
  pasoActual = 'seguridad cliente';
  const r = await page.goto(B + '/ver_pedido.php?id=' + ids.pedido_otro);
  ok(r.status() === 404, 'no puede ver pedido ajeno (404)');
  const rpdf = await page.request.get(B + '/generar_comprobante.php?id=' + ids.pedido_otro);
  ok(rpdf.status() === 404, 'no puede descargar comprobante ajeno');
  const radm = await page.goto(B + '/panel_admin.php');
  ok(radm.status() === 403, 'cliente no entra al panel admin (403)');
  const rexp = await page.request.get(B + '/exportar_usuarios.php', { maxRedirects: 0 });
  ok(rexp.status() === 303, 'cliente no puede exportar usuarios');
  // Historial
  pasoActual = 'historial';
  await page.goto(B + '/mis_pedidos.php');
  ok(await page.locator('.pedido-card').count() === 2, 'historial con pedido nuevo + antiguo');
  ok(await page.locator('.pedido-card', { hasText: 'Pagado' }).count() === 2, 'pedido antiguo muestra estado Pagado');
  await page.goto(B + '/ver_pedido.php?id=' + ids.pedido_cliente);
  ok(await page.locator('td[data-label="Producto"]', { hasText: 'Enchape castillo' }).count() === 1, 'detalle de pedido antiguo');
  await Promise.all([page.waitForNavigation(), page.click('button:has-text("Repetir pedido")')]);
  ok(page.url().includes('ver_carrito.php') && await page.locator('.carrito-item').count() === 1, 'repetir pedido llena el carrito');
  // Mi cuenta
  pasoActual = 'mi cuenta';
  await page.goto(B + '/mi_cuenta.php');
  await page.fill('#nombre', 'Laura Gómez Pérez');
  await Promise.all([page.waitForNavigation(), page.click('form:has(input[value=perfil]) button[type=submit]')]);
  ok(await page.locator('.alert-success').count() === 1, 'actualizar nombre');
  // Responsive
  pasoActual = 'responsive';
  for (const [w, h, n] of [[360, 740, 'android'], [768, 1024, 'tablet']]) {
    await page.setViewportSize({ width: w, height: h });
    for (const p of ['/tienda.php', '/ver_carrito.php', '/mis_pedidos.php', '/ver_pedido.php?id=' + ids.pedido_cliente, '/productos.php', '/index.php', '/contacto.php']) {
      await page.goto(B + p);
      await sinScrollHorizontal(page, n + ' ' + p);
    }
  }
  await page.setViewportSize({ width: 360, height: 740 });
  await page.goto(B + '/tienda.php');
  await page.screenshot({ path: '/tmp/movil_tienda.png', fullPage: false });
  await ctx.close();

  // ===== Administrador =====
  ctx = await browser.newContext({ viewport: { width: 1366, height: 900 } });
  await rutas(ctx);
  page = await nuevaPagina(ctx);
  pasoActual = 'admin';
  console.log('Administrador');
  await page.goto(B + '/login.php');
  await page.fill('#correo', 'admin@ceramicentro.test');
  await page.fill('#clave', 'Admin12345');
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  ok(page.url().includes('panel_admin.php'), 'login admin');
  ok(await page.locator('.kpi-valor').count() >= 6, 'dashboard con indicadores');
  await page.screenshot({ path: '/tmp/admin_dashboard.png', fullPage: true });
  for (const p of ['ver_productos.php', 'ver_productos.php?stock=agotado', 'agregar_producto.php', 'agregar_producto.php?tab=categorias', 'inventario.php', 'historial_pedidos_admin.php',
                   'estadisticas_ventas.php', 'historial_productos.php', 'ver_usuarios.php', 'crear_admin.php', 'mensajes_contacto.php', 'test_mongo.php', 'editar_producto.php?id=' + ids.producto_castillo]) {
    const resp = await page.goto(B + '/' + p);
    ok(resp.status() === 200, 'admin ' + p + ' carga');
  }
  // Crear categoría y producto
  pasoActual = 'admin productos';
  await page.goto(B + '/agregar_producto.php?tab=categorias');
  await page.fill('#nombre_categoria', 'Pegantes');
  await Promise.all([page.waitForNavigation(), page.click('button:has-text("Agregar categoría")')]);
  ok(await page.locator('.alert-success', { hasText: 'Pegantes' }).count() === 1, 'categoría creada');
  await page.goto(B + '/agregar_producto.php');
  await page.fill('#nombre', 'Pegante cerámico 25kg');
  await page.fill('#precio', '45000');
  await page.fill('#stock', '10');
  await page.fill('#descripcion', 'Pegante para cerámica de alta adherencia.');
  await page.selectOption('#categoria', { label: 'Pegantes' });
  await page.setInputFiles('#imagen', __dirname + '/prueba.png');
  await Promise.all([page.waitForNavigation(), page.click('button:has-text("Guardar producto")')]);
  ok(await page.locator('.alert-success', { hasText: 'Pegante cerámico' }).count() === 1, 'producto creado con imagen');
  // Subir un archivo PHP disfrazado de imagen debe fallar
  await page.fill('#nombre', 'Malicioso');
  await page.fill('#precio', '1000');
  await page.fill('#stock', '1');
  await page.fill('#descripcion', 'intento de subir php');
  await page.selectOption('#categoria', { label: 'Pegantes' });
  await page.setInputFiles('#imagen', __dirname + '/falsa.jpg');
  await Promise.all([page.waitForNavigation(), page.click('button:has-text("Guardar producto")')]);
  ok(await page.locator('#err-imagen').count() === 1, 'archivo no-imagen rechazado');
  // Editar producto
  await page.goto(B + '/editar_producto.php?id=' + ids.producto_castillo);
  await page.fill('#precio', '60000');
  await page.selectOption('#categoria', { label: 'Baldosas' });
  await Promise.all([page.waitForNavigation(), page.click('button:has-text("Guardar cambios")')]);
  ok(await page.locator('.alert-success', { hasText: '2 cambios' }).count() === 1, 'edición registra 2 cambios');
  await page.goto(B + '/historial_productos.php');
  ok(await page.locator('td', { hasText: 'Enchapes' }).count() >= 1, 'historial guarda categoría anterior');
  // Inventario
  pasoActual = 'admin inventario';
  await page.goto(B + '/inventario.php?filtro=agotado');
  const filaInv = page.locator('tr', { hasText: 'hexagonal' });
  await filaInv.locator('input[name=stock]').fill('20');
  await Promise.all([page.waitForNavigation(), filaInv.locator('button').click()]);
  ok(await page.locator('.alert-success', { hasText: '20 unidades' }).count() === 1, 'ajuste de inventario');
  // Pedidos: cambiar estado
  pasoActual = 'admin pedidos';
  await page.goto(B + '/historial_pedidos_admin.php');
  ok(await page.locator('.pedido-card').count() === 3, 'lista de pedidos (3)');
  page.once('dialog', d => d.accept());
  const primera = page.locator('.pedido-card').first();
  await primera.locator('select[name=estado]').selectOption('Enviado');
  await Promise.all([page.waitForNavigation(), primera.locator('button:has-text("Actualizar")').click()]);
  ok(await page.locator('.alert-success', { hasText: 'Enviado' }).count() === 1, 'estado de pedido actualizado');
  await page.goto(B + '/historial_pedidos_admin.php?cliente=laura');
  ok(await page.locator('.pedido-card').count() === 2, 'filtro por cliente');
  await page.goto(B + '/historial_pedidos_admin.php?numero=CS-000001');
  ok(await page.locator('.pedido-card').count() === 1, 'filtro por número');
  // Exportaciones
  pasoActual = 'exportaciones';
  for (const e of ['exportar_productos.php', 'exportar_usuarios.php', 'exportar_historial_excel.php', 'exportar_estadisticas_excel.php', 'exportar_pedidos_excel.php']) {
    const rr = await page.request.get(B + '/' + e);
    const body = await rr.body();
    ok(rr.status() === 200 && body.slice(0, 2).toString() === 'PK' && /\.xlsx/.test(rr.headers()['content-disposition'] || ''), 'Excel ' + e + ' (' + (rr.headers()['content-disposition'] || '') + ')');
    fs.writeFileSync('/tmp/' + e.replace('.php', '.xlsx'), body);
  }
  for (const e of ['reporte_ventas_pdf.php', 'admin_pedido_pdf.php?id=' + ids.pedido_otro]) {
    const rr = await page.request.get(B + '/' + e);
    const body = await rr.body();
    ok(rr.status() === 200 && body.slice(0, 4).toString() === '%PDF', 'PDF ' + e);
    fs.writeFileSync('/tmp/' + e.split('?')[0].replace('.php', '.pdf'), body);
  }
  // Responsive admin
  pasoActual = 'responsive admin';
  await page.setViewportSize({ width: 360, height: 740 });
  for (const p of ['/panel_admin.php', '/ver_productos.php', '/historial_pedidos_admin.php', '/inventario.php', '/ver_usuarios.php', '/historial_productos.php', '/estadisticas_ventas.php', '/agregar_producto.php']) {
    await page.goto(B + p);
    await sinScrollHorizontal(page, 'admin móvil ' + p);
  }
  await page.goto(B + '/ver_productos.php');
  await page.screenshot({ path: '/tmp/admin_movil_productos.png' });
  await ctx.close();
  await browser.close();

  console.log('\n===== PROBLEMAS (' + problemas.length + ') =====');
  problemas.forEach(p => console.log(p));
  process.exit(problemas.length ? 1 : 0);
})().catch(e => { console.error(e); console.log(problemas.join('\n')); process.exit(2); });
