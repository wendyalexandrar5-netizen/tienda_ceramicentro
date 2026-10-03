<?php
/** Gestión de usuarios: listado, búsqueda, cambio de rol / nombre, activar / desactivar y eliminar. */
require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/seguridad.php';
require_once __DIR__ . '/includes/filtros_usuarios.php';
requerir_sesion('administrador');

$col = mongo()->selectCollection('usuarios');
$yo = usuario_actual()['id'];

if (es_post()) {
    csrf_verificar();
    $id = oid((string)($_POST['id'] ?? ''));
    $u = $id ? $col->findOne(['_id' => $id]) : null;
    $accion = (string)($_POST['accion'] ?? '');
    if (!$u) {
        flash('error', 'El usuario no existe.');
    } elseif ((string)$u['_id'] === $yo && in_array($accion, ['rol', 'estado', 'eliminar'], true)) {
        flash('warning', 'No puedes cambiar el rol, desactivar ni eliminar tu propia cuenta.');
    } elseif ($accion === 'rol') {
        $rol = in_array($_POST['rol'] ?? '', ['cliente', 'administrador'], true) ? $_POST['rol'] : null;
        if ($rol && $rol !== ($u['rol'] ?? '')) {
            $col->updateOne(['_id' => $id], ['$set' => ['rol' => $rol, 'actualizado_en' => nowUTC()]]);
            mongo()->selectCollection('tokens_app')->deleteMany(['usuario_id' => $id]);
            log_app('info', 'Cambio de rol', ['usuario' => (string)$id, 'rol' => $rol, 'por' => $yo]);
            flash('success', 'El rol de ' . $u['nombre'] . ' ahora es «' . $rol . '».');
        }
    } elseif ($accion === 'nombre') {
        $nombre = trim(preg_replace('/\s+/u', ' ', (string)($_POST['nombre'] ?? '')));
        if ($m = validar_nombre($nombre)) {
            flash('error', $m);
        } else {
            $col->updateOne(['_id' => $id], ['$set' => ['nombre' => $nombre, 'actualizado_en' => nowUTC()]]);
            flash('success', 'Nombre actualizado.');
        }
    } elseif ($accion === 'estado') {
        $activo = ($u['activo'] ?? true) === false;
        $col->updateOne(['_id' => $id], ['$set' => ['activo' => $activo, 'actualizado_en' => nowUTC()]]);
        if (!$activo) {
            mongo()->selectCollection('tokens_app')->deleteMany(['usuario_id' => $id]);
        }
        flash('success', 'La cuenta de ' . $u['nombre'] . ($activo ? ' fue activada.' : ' fue desactivada (ya no podrá iniciar sesión).'));
    } elseif ($accion === 'eliminar') {
        if (mongo()->selectCollection('pedidos')->countDocuments(['usuario_id' => $id], ['limit' => 1]) > 0) {
            flash('warning', 'No se puede eliminar a ' . $u['nombre'] . ' porque tiene pedidos registrados. Puedes desactivar su cuenta.');
        } else {
            $col->deleteOne(['_id' => $id]);
            mongo()->selectCollection('tokens_app')->deleteMany(['usuario_id' => $id]);
            log_app('info', 'Usuario eliminado', ['usuario' => (string)$id, 'por' => $yo]);
            flash('success', 'Usuario eliminado.');
        }
    }
    header('Location: ' . url('ver_usuarios.php', array_filter(['buscar' => $_GET['buscar'] ?? '', 'rol' => $_GET['rol'] ?? '', 'pagina' => $_GET['pagina'] ?? ''])), true, 303);
    exit;
}

[$f, $q] = filtros_usuarios();
$porPagina = 25;
$total = $col->countDocuments($q);
$paginas = max(1, (int)ceil($total / $porPagina));
$pagina = min($paginas, max(1, (int)($_GET['pagina'] ?? 1)));
$usuarios = iterator_to_array($col->find($q, ['sort' => ['nombre' => 1], 'skip' => ($pagina - 1) * $porPagina, 'limit' => $porPagina,
    'projection' => ['nombre' => 1, 'correo' => 1, 'rol' => 1, 'activo' => 1, 'createdAt' => 1, 'origen' => 1]]), false);
$conPedidos = [];
if ($usuarios) {
    foreach (mongo()->selectCollection('pedidos')->aggregate([
        ['$match' => ['usuario_id' => ['$in' => array_map(fn($u) => $u['_id'], $usuarios)]]],
        ['$group' => ['_id' => '$usuario_id', 'n' => ['$sum' => 1]]],
    ]) as $r) {
        $conPedidos[(string)$r['_id']] = (int)$r['n'];
    }
}
$params = array_filter($f);
$accionUrl = url('ver_usuarios.php', array_merge($params, $pagina > 1 ? ['pagina' => $pagina] : []));

admin_inicio('Usuarios', 'usuarios', ['acciones' =>
    '<a href="' . e(url('crear_admin.php')) . '" class="btn btn-cs"><i class="bi bi-person-plus" aria-hidden="true"></i> Añadir administrador</a>'
    . boton_exportar('exportar_usuarios.php', $params)]);
?>
<form method="get" class="cs-panel mb-4 d-flex flex-wrap gap-2 align-items-end" role="search" data-sin-bloqueo>
    <div class="flex-grow-1"><label class="form-label small" for="buscar">Buscar por nombre o correo</label><input type="search" id="buscar" name="buscar" class="form-control" value="<?= e($f['buscar']) ?>"></div>
    <div><label class="form-label small" for="rol">Rol</label>
        <select id="rol" name="rol" class="form-select"><option value="">Todos</option><option value="cliente" <?= $f['rol'] === 'cliente' ? 'selected' : '' ?>>Clientes</option><option value="administrador" <?= $f['rol'] === 'administrador' ? 'selected' : '' ?>>Administradores</option></select></div>
    <button class="btn btn-cs" type="submit">Filtrar</button>
    <?php if ($params): ?><a class="btn btn-outline-secondary" href="<?= e(url('ver_usuarios.php')) ?>">Limpiar</a><?php endif; ?>
</form>
<p class="text-secondary small" role="status"><?= (int)$total ?> usuario(s)<?= $paginas > 1 ? ' · página ' . $pagina . ' de ' . $paginas : '' ?></p>
<div class="cs-panel p-0 p-md-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle tabla-apilable mb-0">
            <thead><tr><th scope="col">Nombre</th><th scope="col">Correo</th><th scope="col">Rol</th><th scope="col">Estado</th><th scope="col">Pedidos</th><th scope="col">Registro</th><th scope="col">Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($usuarios as $u): $uid = (string)$u['_id']; $esYo = $uid === $yo; $activo = ($u['activo'] ?? true) !== false; $np = $conPedidos[$uid] ?? 0; ?>
                <tr>
                    <td data-label="Nombre" class="fw-semibold"><?= e($u['nombre'] ?? '') ?><?= $esYo ? ' <span class="badge text-bg-secondary">Tú</span>' : '' ?></td>
                    <td data-label="Correo"><?= e($u['correo'] ?? '') ?></td>
                    <td data-label="Rol"><span class="estado-badge <?= ($u['rol'] ?? '') === 'administrador' ? 'estado-proceso' : 'estado-neutro' ?>"><?= e($u['rol'] ?? '—') ?></span></td>
                    <td data-label="Estado"><span class="estado-badge <?= $activo ? 'estado-ok' : 'estado-error' ?>"><?= $activo ? 'Activo' : 'Desactivado' ?></span></td>
                    <td data-label="Pedidos"><?= $np ? '<a href="' . e(url('historial_pedidos_admin.php', ['cliente' => $u['correo'] ?? ''])) . '">' . $np . '</a>' : '0' ?></td>
                    <td data-label="Registro" class="small"><?= !empty($u['createdAt']) ? e(fecha_local($u['createdAt'], 'd/m/Y')) : '—' ?></td>
                    <td data-label="Acciones" class="celda-acciones">
                        <?php if (!$esYo): ?>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Gestionar</button>
                            <div class="dropdown-menu dropdown-menu-end p-3" style="min-width:260px">
                                <form method="post" action="<?= e($accionUrl) ?>" class="mb-3" data-confirmar="¿Cambiar el rol de <?= e($u['nombre'] ?? '') ?>?">
                                    <?= csrf_campo() ?><input type="hidden" name="id" value="<?= e($uid) ?>"><input type="hidden" name="accion" value="rol">
                                    <label class="form-label small" for="rol-<?= e($uid) ?>">Rol</label>
                                    <div class="d-flex gap-1"><select id="rol-<?= e($uid) ?>" name="rol" class="form-select form-select-sm">
                                        <option value="cliente" <?= ($u['rol'] ?? '') === 'cliente' ? 'selected' : '' ?>>Cliente</option>
                                        <option value="administrador" <?= ($u['rol'] ?? '') === 'administrador' ? 'selected' : '' ?>>Administrador</option>
                                    </select><button class="btn btn-sm btn-cs" type="submit">OK</button></div>
                                </form>
                                <form method="post" action="<?= e($accionUrl) ?>" class="mb-3">
                                    <?= csrf_campo() ?><input type="hidden" name="id" value="<?= e($uid) ?>"><input type="hidden" name="accion" value="nombre">
                                    <label class="form-label small" for="nom-<?= e($uid) ?>">Nombre</label>
                                    <div class="d-flex gap-1"><input id="nom-<?= e($uid) ?>" name="nombre" class="form-control form-control-sm" value="<?= e($u['nombre'] ?? '') ?>" required minlength="3" maxlength="50"><button class="btn btn-sm btn-cs" type="submit">OK</button></div>
                                </form>
                                <form method="post" action="<?= e($accionUrl) ?>" class="mb-2" data-confirmar="¿<?= $activo ? 'Desactivar' : 'Activar' ?> la cuenta de <?= e($u['nombre'] ?? '') ?>?">
                                    <?= csrf_campo() ?><input type="hidden" name="id" value="<?= e($uid) ?>"><input type="hidden" name="accion" value="estado">
                                    <button class="btn btn-sm w-100 <?= $activo ? 'btn-outline-warning' : 'btn-outline-success' ?>" type="submit"><?= $activo ? 'Desactivar cuenta' : 'Activar cuenta' ?></button>
                                </form>
                                <form method="post" action="<?= e($accionUrl) ?>" data-confirmar="¿Eliminar definitivamente a <?= e($u['nombre'] ?? '') ?>?">
                                    <?= csrf_campo() ?><input type="hidden" name="id" value="<?= e($uid) ?>"><input type="hidden" name="accion" value="eliminar">
                                    <button class="btn btn-sm btn-outline-danger w-100" type="submit" <?= $np ? 'disabled title="Tiene pedidos: solo se puede desactivar"' : '' ?>>Eliminar usuario</button>
                                </form>
                            </div>
                        </div>
                        <?php else: ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('mi_cuenta.php')) ?>">Mi cuenta</a><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$usuarios): ?><tr><td colspan="7" class="text-center text-secondary py-4">No hay usuarios con estos filtros.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php paginacion_html($pagina, $paginas, 'ver_usuarios.php', $params); ?>
<?php admin_fin(); ?>
