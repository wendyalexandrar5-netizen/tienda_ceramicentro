<?php
/** Página mostrada cuando un usuario intenta entrar a un área que no corresponde a su rol. */
require_once __DIR__ . '/includes/bootstrap.php';
log_app('aviso', 'Acceso denegado', ['usuario' => usuario_actual()['id'] ?? null, 'desde' => $_SERVER['HTTP_REFERER'] ?? '']);
mostrar_error(
    403,
    'Acceso denegado',
    usuario_actual()
        ? 'Tu cuenta no tiene permisos para entrar a esta sección.'
        : 'Debes iniciar sesión con una cuenta autorizada para ver esta sección.'
);
