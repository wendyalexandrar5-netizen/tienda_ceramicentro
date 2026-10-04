// Pruebas E2E de: reserva vencida, gestión de pedidos del admin y recuperación de contraseña
const { chromium } = require('/opt/node22/lib/node_modules/playwright');
const { execSync } = require('child_process');
const B = 'http://127.0.0.1:8080/tiendaonline_mongodb';
const RAIZ = require('path').resolve(__dirname, '../..');
const problemas = []; let paso = '';
function ok(c, m) { if (!c) { problemas.push('[' + paso + '] ' + m); console.log('  ✘ ' + m); } else console.log('  ✔ ' + m); }
function php(codigo) {
  codigo = `require "${RAIZ}/tests/fake_mongo.php"; ` + codigo;
  return execSync(`php -r '${codigo.replace(/'/g, "'\\''")}'`, { env: { ...process.env, CS_FAKE_DB: process.env.CS_FAKE_DB || '/tmp/cs_db.ser' } }).toString();
}
const stock = (nombre) => +php(`require "${RAIZ}/includes/bootstrap.php"; echo mongo()->selectCollection("productos")->findOne(["nombre"=>"${nombre}"])["stock"];`);
async function login(p, c, k) { await p.goto(B + '/login.php'); await p.fill('#correo', c); await p.fill('#clave', k); await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]); }
(async () => {
  const b = await chromium.launch();
  const ctx = await b.newContext();
  await ctx.route(/cdn\.jsdelivr|fonts\.|google\.com/, r => r.fulfill({ status: 200, body: '' }));
  await ctx.addInitScript(() => { try { localStorage.setItem('cs_consentimiento', 'necesarias'); } catch (e) {} });
  const p = await ctx.newPage();
  p.on('pageerror', e => problemas.push('[' + paso + '] JS ' + e.message));
  p.on('dialog', d => d.accept());

  paso = 'reserva vencida';
  const inicial = stock('Lavamanos negro');
  await login(p, 'pedro@correo.test', 'Pedro12345');
  await p.goto(B + '/tienda.php?q=Lavamanos');
  const f = p.locator('.producto-card', { hasText: 'Lavamanos negro' }).locator('form');
  await f.locator('input[name=cantidad]').fill('3');
  await Promise.all([p.waitForResponse(r => r.url().includes('agregar_carrito.php')), f.locator('button').click()]);
  await p.goto(B + '/realizar_pedido.php');
  await Promise.all([p.waitForNavigation(), p.click('button:has-text("Confirmar y pagar")')]);
  const pid = new URL(p.url()).searchParams.get('id');
  ok(stock('Lavamanos negro') === inicial - 3, 'confirmar pedido reserva 3 unidades');
  ok(await p.locator('text=están reservados hasta').count() === 1, 'muestra fecha límite de la reserva');
  // Simula que pasaron 25 horas
  php(`require "${RAIZ}/includes/pedidos.php"; $c=mongo()->selectCollection("pedidos"); $f=new MongoDB\\BSON\\UTCDateTime((time()-25*3600)*1000); $c->updateOne(["_id"=>oid("${pid}")],["\\$set"=>["fecha"=>$f,"historial_estados"=>[["estado"=>"Pendiente de pago","fecha"=>$f,"por"=>"sistema"]]]]); mongo()->selectCollection("contadores")->deleteOne(["_id"=>"limpieza_reservas"]);`);
  await p.goto(B + '/mis_pedidos.php');
  ok(stock('Lavamanos negro') === inicial, 'reserva vencida devuelve el inventario');
  ok(await p.locator('.pedido-card', { hasText: 'Cancelado' }).count() === 1, 'pedido vencido queda Cancelado');
  await p.goto(B + '/ver_pedido.php?id=' + pid);
  ok(await p.locator('text=se canceló automáticamente').count() === 1, 'explica la cancelación automática');
  await p.goto(B + '/mis_pedidos.php');
  ok(stock('Lavamanos negro') === inicial, 'no devuelve el stock dos veces');

  paso = 'admin pedidos';
  // Nuevo pedido pendiente de Pedro con 2 unidades
  await p.goto(B + '/tienda.php?q=Lavamanos');
  await p.locator('.producto-card', { hasText: 'Lavamanos negro' }).locator('input[name=cantidad]').fill('2');
  await Promise.all([p.waitForResponse(r => r.url().includes('agregar_carrito.php')), p.locator('.producto-card', { hasText: 'Lavamanos negro' }).locator('form button').click()]);
  await p.goto(B + '/realizar_pedido.php');
  await Promise.all([p.waitForNavigation(), p.click('button:has-text("Confirmar y pagar")')]);
  const pid2 = new URL(p.url()).searchParams.get('id');
  ok(stock('Lavamanos negro') === inicial - 2, 'nuevo pedido reserva 2');
  await p.goto(B + '/logout.php');
  await login(p, 'admin@ceramicentro.test', 'Admin12345');
  await p.goto(B + '/admin_pedido.php?id=' + pid2);
  await p.locator('input[name^="cantidad["]').fill('4');
  await Promise.all([p.waitForNavigation(), p.click('button:has-text("Guardar cantidades")')]);
  ok(await p.locator('.alert-success', { hasText: '$840.000' }).count() === 1, 'admin aumenta cantidad y recalcula total');
  ok(stock('Lavamanos negro') === inicial - 4, 'inventario ajustado (+2 reservadas)');
  await p.fill('#notas', 'Entregar en portería');
  await Promise.all([p.waitForNavigation(), p.click('button:has-text("Guardar notas")')]);
  ok(await p.locator('#notas').inputValue() === 'Entregar en portería', 'notas internas guardadas');
  ok(await p.locator('button:has-text("Eliminar pedido")').count() === 0, 'no se puede eliminar un pedido activo');
  await p.selectOption('#estado', 'Cancelado');
  await Promise.all([p.waitForNavigation(), p.click('button:has-text("Actualizar")')]);
  ok(stock('Lavamanos negro') === inicial, 'cancelar devuelve las 4 unidades');
  ok(await p.locator('input[name^="cantidad["]').count() === 0, 'pedido cancelado ya no es editable');
  await Promise.all([p.waitForNavigation(), p.click('button:has-text("Eliminar pedido")')]);
  ok(p.url().includes('historial_pedidos_admin.php') && await p.locator('.alert-success', { hasText: 'eliminado' }).count() === 1, 'pedido cancelado eliminado');
  ok(stock('Lavamanos negro') === inicial, 'eliminar no altera el inventario');
  const r404 = await p.goto(B + '/admin_pedido.php?id=' + pid2);
  ok(p.url().includes('historial_pedidos_admin.php'), 'pedido eliminado ya no existe');
  await p.goto(B + '/logout.php');

  paso = 'recuperar clave';
  await p.goto(B + '/login.php');
  await Promise.all([p.waitForNavigation(), p.click('text=¿Olvidaste tu contraseña?')]);
  await p.fill('#correo', 'noexiste@correo.test');
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  ok(await p.locator('.alert-success').count() === 1 && await p.locator('text=Abrir enlace').count() === 0, 'correo inexistente: mismo mensaje, sin enlace');
  await p.goto(B + '/recuperar_clave.php');
  await p.fill('#correo', 'LAURA@correo.test');
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  const enlace = await p.locator('a:has-text("Abrir enlace")').getAttribute('href');
  ok(!!enlace, 'modo desarrollo muestra el enlace (sin SMTP)');
  await p.goto(enlace);
  await p.fill('#nueva', 'NuevaClave2026'); await p.fill('#confirmar', 'NuevaClave2026');
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  ok(p.url().includes('login.php'), 'contraseña restablecida');
  const usado = await p.goto(enlace);
  ok(usado.status() === 410, 'el enlace no se puede reutilizar');
  await login(p, 'laura@correo.test', 'NuevaClave2026');
  ok(p.url().includes('tienda.php'), 'login con la nueva contraseña');
  const malo = await p.goto(B + '/restablecer_clave.php?token=' + 'a'.repeat(64));
  ok(malo.status() === 410, 'token inválido rechazado');

  paso = 'legales';
  for (const pg of ['tyc.php', 'aviso_legal.php', 'politica_privacidad.php', 'politica_cookies.php']) {
    await p.goto(B + '/' + pg);
    ok(await !(await p.content()).includes('[PENDIENTE') && await p.locator('text=Proyecto académico').count() >= 1, pg + ' completo con aviso académico');
  }
  await b.close();
  console.log('PROBLEMAS: ' + problemas.length); problemas.forEach(x => console.log(x));
  process.exit(problemas.length ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
