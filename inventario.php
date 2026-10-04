<?php
/** Control de inventario: existencias, valor del inventario y ajustes de stock con registro en historial. */
require_once __DIR__ . '/includes/pedidos.php';
require_once __DIR__ . '/includes/admin.php';
requerir_sesion('administrador');
liberar_pedidos_vencidos(); // devuelve el inventario de pedidos sin pagar cuya reserva venció

$col = mongo()->selectCollection('productos');
$motivos = ['Ingreso de mercancía', 'Ajuste por conteo físico', 'Devolución de cliente', 'Producto dañado o perdido', 'Otro'];

if (es_post()) {
    csrf_verificar();
    $p = producto_por_id((string)($_POST['id'] ?? ''));
    $nuevo = (string)($_POST['stock'] ?? '');
    $actual = (int)($_POST['stock_actual'] ?? -1);
    $motivo = in_array($_POST['motivo'] ?? '', $motivos, true) ? $_POST['motivo'] : 'Otro';
    if (!$p) {
        flash('error', 'El producto no existe.');
    } elseif (!ctype_digit($nuevo) || (int)$nuevo > 1000000) {
        flash('error', 'El stock debe ser un número entero igual o mayor que 0.');
    } elseif ((int)$nuevo === (int)($p['stock'] ?? 0)) {
        flash('info', 'El stock de «' . $p['nombre'] . '» no cambió.');
    } else {
        // Solo actualiza si nadie vendió/modificó el producto mientras tanto
        $r = $col->updateOne(['_id' => $p['_id'], 'stock' => $actual], ['$set' => ['stock' => (int)$nuevo, 'actualizado_en' => nowUTC()]]);
        if ($r->getModifiedCount() === 1) {
            registrar_historial('edito', $p, ['Stock: ' . $actual . ' → ' . (int)$nuevo, 'Motivo: ' . $motivo . ' (inventario)']);
            flash('success', 'Stock de «' . $p['nombre'] . '» actualizado a ' . (int)$nuevo . ' unidades.');
        } else {
            flash('warning', 'El stock de «' . $p['nombre'] . '» cambió mientras lo editabas (por ejemplo, por una venta). Revisa el valor actual e intenta de nuevo.');
        }
    }
    redirigir('inventario.php', array_filter(['filtro' => $_GET['filtro'] ?? '', 'buscar' => $_GET['buscar'] ?? '']));
}

$filtroSel = in_array($_GET['filtro'] ?? '', ['bajo', 'agotado'], true) ? $_GET['filtro'] : '';
$buscar = mb_substr(trim((string)($_GET['buscar'] ?? '')), 0, 80);
$q = [];
if ($filtroSel === 'bajo') {
    $q['stock'] = ['$lte' => 5];
} elseif ($filtroSel === 'agotado') {
    $q['stock'] = ['$lte' => 0];
}
if ($buscar !== '') {
    $q['nombre'] = ['$regex' => regex_literal($buscar), '$options' => 'i'];
}

$resumenInv = $col->aggregate([['$group' => [
    '_id' => null, 'unidades' => ['$sum' => '$stock'], 'valor' => ['$sum' => ['$multiply' => ['$precio', '$stock']]], 'n' => ['$sum' => 1],
]]])->toArray();
$agotados = $col->countDocuments(['stock' => ['$lte' => 0]]);
$bajos = $col->countDocuments(['stock' => ['$gt' => 0, '$lte' => 5]]);
$productos = iterator_to_array($col->find($q, ['sort' => ['stock' => 1, 'nombre' => 1], 'limit' => 200]), false);
$mapa = categorias_mapa();

admin_inicio('Control de inventario', 'inventario', ['acciones' => boton_exportar('exportar_productos.php', ['orden' => 'stock_asc'], 'Exportar inventario')]);
?>
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3"><div class="kpi"><div class="kpi-icono" aria-hidden="true"><i class="bi bi-boxes"></i></div><div><div class="kpi-valor"><?= number_format((int)($resumenInv[0]['unidades'] ?? 0), 0, ',', '.') ?></div><div class="kpi-etiqueta">Unidades en inventario</div></div></div></div>
    <div class="col-6 col-xl-3"><div class="kpi"><div class="kpi-icono" aria-hidden="true"><i class="bi bi-cash-stack"></i></div><div><div class="kpi-valor"><?= e(dinero($resumenInv[0]['valor'] ?? 0)) ?></div><div class="kpi-etiqueta">Valor a precio de venta</div></div></div></div>
    <div class="col-6 col-xl-3"><a class="kpi kpi-alerta text-reset text-decoration-none" href="<?= e(url('inventario.php', ['filtro' => 'bajo'])) ?>"><div class="kpi-icono" aria-hidden="true"><i class="bi bi-exclamation-triangle"></i></div><div><div class="kpi-valor"><?= (int)$bajos ?></div><div class="kpi-etiqueta">Con stock bajo (1-5)</div></div></a></div>
    <div class="col-6 col-xl-3"><a class="kpi kpi-alerta text-reset text-decoration-none" href="<?= e(url('inventario.php', ['filtro' => 'agotado'])) ?>"><div class="kpi-icono" aria-hidden="true"><i class="bi bi-x-octagon"></i></div><div><div class="kpi-valor"><?= (int)$agotados ?></div><div class="kpi-etiqueta">Agotados</div></div></a></div>
</div>

<form method="get" class="d-flex flex-wrap gap-2 mb-3" role="search" data-sin-bloqueo>
    <label class="visually-hidden" for="buscarInv">Buscar producto</label>
    <input type="search" id="buscarInv" name="buscar" class="form-control" style="max-width:320px" placeholder="Buscar producto…" value="<?= e($buscar) ?>">
    <label class="visually-hidden" for="filtroInv">Filtro</label>
    <select id="filtroInv" name="filtro" class="form-select" style="max-width:220px">
        <option value="">Todos los productos</option>
        <option value="bajo" <?= $filtroSel === 'bajo' ? 'selected' : '' ?>>Stock bajo y agotados</option>
        <option value="agotado" <?= $filtroSel === 'agotado' ? 'selected' : '' ?>>Solo agotados</option>
    </select>
    <button class="btn btn-cs" type="submit">Filtrar</button>
    <?php if ($filtroSel || $buscar !== ''): ?><a class="btn btn-outline-secondary" href="<?= e(url('inventario.php')) ?>">Limpiar</a><?php endif; ?>
</form>

<div class="cs-panel p-0 p-md-3">
    <div class="table-responsive" tabindex="0" role="region" aria-label="Tabla (desplazable horizontalmente)">
        <table class="table table-hover align-middle tabla-apilable mb-0">
            <thead><tr><th scope="col">Producto</th><th scope="col">Categoría</th><th scope="col" class="text-end">Precio</th><th scope="col" class="text-end">Stock actual</th><th scope="col">Ajustar stock</th></tr></thead>
            <tbody>
            <?php foreach ($productos as $p): $s = (int)($p['stock'] ?? 0); $pid = (string)$p['_id']; ?>
                <tr>
                    <td data-label="Producto" class="fw-semibold"><?= e($p['nombre'] ?? '') ?></td>
                    <td data-label="Categoría"><?= e($mapa[(string)($p['categoria_id'] ?? '')] ?? 'Sin categoría') ?></td>
                    <td data-label="Precio" class="text-md-end"><?= e(dinero($p['precio'] ?? 0)) ?></td>
                    <td data-label="Stock actual" class="text-md-end <?= $s <= 0 ? 'stock-cero' : ($s <= 5 ? 'stock-bajo' : '') ?>"><?= $s ?></td>
                    <td data-label="Ajustar stock" class="celda-acciones">
                        <form method="post" class="d-flex flex-wrap gap-1 justify-content-end justify-content-md-start" action="<?= e(url('inventario.php', array_filter(['filtro' => $filtroSel, 'buscar' => $buscar]))) ?>">
                            <?= csrf_campo() ?>
                            <input type="hidden" name="id" value="<?= e($pid) ?>">
                            <input type="hidden" name="stock_actual" value="<?= $s ?>">
                            <label class="visually-hidden" for="st-<?= e($pid) ?>">Nuevo stock de <?= e($p['nombre'] ?? '') ?></label>
                            <input type="number" id="st-<?= e($pid) ?>" name="stock" min="0" step="1" value="<?= $s ?>" class="form-control form-control-sm" style="width:90px" required>
                            <label class="visually-hidden" for="mo-<?= e($pid) ?>">Motivo</label>
                            <select id="mo-<?= e($pid) ?>" name="motivo" class="form-select form-select-sm" style="width:auto">
                                <?php foreach ($motivos as $m): ?><option><?= e($m) ?></option><?php endforeach; ?>
                            </select>
                            <button class="btn btn-sm btn-cs" type="submit" data-cargando="…">Guardar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$productos): ?><tr><td colspan="5" class="text-center text-secondary py-4">No hay productos con este filtro.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<p class="small text-secondary mt-3">Cada ajuste queda registrado en el <a href="<?= e(url('historial_productos.php')) ?>">historial de productos</a>. Las ventas descuentan el inventario automáticamente y los pedidos cancelados o con pago rechazado lo devuelven.</p>
<?php admin_fin(); ?>
