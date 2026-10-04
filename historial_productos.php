<?php
/** Historial de cambios de productos (auditoría de administradores). */
require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/filtros_historial.php';
requerir_sesion('administrador');

[$f, $q] = filtros_historial();
$col = mongo()->selectCollection('historial_productos');
$porPagina = 30;
$total = $col->countDocuments($q);
$paginas = max(1, (int)ceil($total / $porPagina));
$pagina = min($paginas, max(1, (int)($_GET['pagina'] ?? 1)));
$filas = iterator_to_array($col->find($q, ['sort' => ['fecha' => -1], 'skip' => ($pagina - 1) * $porPagina, 'limit' => $porPagina]), false);
$params = array_filter($f);

admin_inicio('Historial de productos', 'historial', ['acciones' => boton_exportar('exportar_historial_excel.php', $params)]);
?>
<form method="get" class="cs-panel mb-4" data-sin-bloqueo>
    <div class="row g-2 align-items-end">
        <div class="col-6 col-md-2"><label class="form-label small" for="fecha_desde">Desde</label><input type="date" id="fecha_desde" name="fecha_desde" class="form-control" value="<?= e($f['fecha_desde']) ?>"></div>
        <div class="col-6 col-md-2"><label class="form-label small" for="fecha_hasta">Hasta</label><input type="date" id="fecha_hasta" name="fecha_hasta" class="form-control" value="<?= e($f['fecha_hasta']) ?>"></div>
        <div class="col-6 col-md-2">
            <label class="form-label small" for="accion">Acción</label>
            <select id="accion" name="accion" class="form-select">
                <option value="">Todas</option>
                <?php foreach (['agrego', 'edito', 'elimino'] as $a): ?><option value="<?= $a ?>" <?= $f['accion'] === $a ? 'selected' : '' ?>><?= e(accion_texto($a)) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-4"><label class="form-label small" for="buscar">Buscar (producto, admin o cambio)</label><input type="search" id="buscar" name="buscar" class="form-control" value="<?= e($f['buscar']) ?>"></div>
        <div class="col-md-2 d-flex gap-2"><button class="btn btn-cs flex-grow-1" type="submit"><i class="bi bi-filter" aria-hidden="true"></i> Filtrar</button>
            <?php if ($params): ?><a href="<?= e(url('historial_productos.php')) ?>" class="btn btn-outline-secondary" aria-label="Limpiar filtros"><i class="bi bi-x-lg" aria-hidden="true"></i></a><?php endif; ?></div>
    </div>
</form>
<p class="text-secondary small" role="status"><?= (int)$total ?> registro(s)<?= $paginas > 1 ? ' · página ' . $pagina . ' de ' . $paginas : '' ?></p>
<div class="cs-panel p-0 p-md-3">
    <div class="table-responsive" tabindex="0" role="region" aria-label="Tabla (desplazable horizontalmente)">
        <table class="table table-hover align-middle tabla-apilable mb-0">
            <thead><tr><th scope="col">Fecha</th><th scope="col">Administrador</th><th scope="col">Acción</th><th scope="col">Producto</th><th scope="col">Categoría antes</th><th scope="col">Categoría después</th><th scope="col">Cambios</th></tr></thead>
            <tbody>
            <?php foreach ($filas as $r): $a = strtolower((string)($r['accion'] ?? '')); $cambios = $r['cambios'] ?? []; ?>
                <tr>
                    <td data-label="Fecha" class="text-nowrap"><?= e(fecha_local($r['fecha'] ?? null, 'd/m/Y h:i a')) ?></td>
                    <td data-label="Administrador"><?= e($r['nombre_admin'] ?? 'Desconocido') ?></td>
                    <td data-label="Acción"><span class="accion-<?= e($a) ?>"><?= e(accion_texto($a)) ?></span></td>
                    <td data-label="Producto" class="fw-semibold"><?= e($r['producto_nombre'] ?? 'N/A') ?></td>
                    <td data-label="Categoría antes"><?= e($r['categoria_anterior'] ?? '—') ?></td>
                    <td data-label="Categoría después"><?= e($r['categoria_nueva'] ?? '—') ?></td>
                    <td data-label="Cambios" class="text-start">
                        <?php if (is_array($cambios)): ?>
                        <ul class="lista-cambios"><?php foreach ($cambios as $c): ?><li><?= e($c) ?></li><?php endforeach; ?></ul>
                        <?php else: ?><?= e($cambios) ?><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$filas): ?><tr><td colspan="7" class="text-center text-secondary py-4">No hay registros con estos filtros.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php paginacion_html($pagina, $paginas, 'historial_productos.php', $params); ?>
<?php admin_fin(); ?>
