<?php
/** Exporta usuarios a Excel (sin contraseñas). Solo administradores. */
require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/excel.php';
require_once __DIR__ . '/includes/filtros_usuarios.php';
requerir_sesion('administrador');

[$f, $q] = filtros_usuarios();
$filas = [];
foreach (mongo()->selectCollection('usuarios')->find($q, ['sort' => ['nombre' => 1], 'projection' => ['nombre' => 1, 'correo' => 1, 'rol' => 1, 'activo' => 1, 'createdAt' => 1]]) as $u) {
    $filas[] = [
        (string)($u['nombre'] ?? ''), (string)($u['correo'] ?? ''), (string)($u['rol'] ?? ''),
        ($u['activo'] ?? true) === false ? 'Desactivado' : 'Activo',
        !empty($u['createdAt']) ? fecha_local($u['createdAt'], 'Y-m-d') : '',
    ];
}
[$libro, $hoja] = excel_nuevo('Usuarios');
excel_tabla($hoja, 'USUARIOS', [
    ['titulo' => 'Nombre', 'ancho' => 30], ['titulo' => 'Correo', 'ancho' => 34], ['titulo' => 'Rol', 'ancho' => 16],
    ['titulo' => 'Estado', 'ancho' => 14], ['titulo' => 'Fecha de registro', 'ancho' => 18],
], $filas, 1, count($filas) . ' usuarios');
excel_enviar($libro, 'usuarios_ceramicentro');
