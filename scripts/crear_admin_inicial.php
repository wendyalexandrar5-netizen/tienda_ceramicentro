<?php
/**
 * Crea un administrador desde la consola (útil con una base de datos nueva, cuando
 * todavía no existe ningún administrador para entrar al panel).
 * Uso:  php scripts/crear_admin_inicial.php "Nombre Apellido" correo@dominio.com
 * La contraseña se pide por teclado (no queda en el historial de comandos).
 */
if (PHP_SAPI !== 'cli') {
    exit("Solo se puede ejecutar desde la línea de comandos.\n");
}
require_once __DIR__ . '/../includes/seguridad.php';

[$_, $nombre, $correo] = array_pad($argv, 3, '');
$correo = normalizar_correo($correo);
if ($m = validar_nombre(trim($nombre))) {
    exit("Nombre inválido: $m\nUso: php scripts/crear_admin_inicial.php \"Nombre Apellido\" correo@dominio.com\n");
}
if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    exit("Correo inválido.\n");
}
if (usuario_por_correo($correo, ['_id' => 1])) {
    exit("Ya existe un usuario con ese correo. Si es cliente, cambia su rol desde el panel o con MongoDB Compass.\n");
}
echo 'Contraseña (mínimo ' . CLAVE_MINIMO . ' caracteres, letras y números): ';
if (stripos(PHP_OS, 'WIN') !== 0) {
    system('stty -echo');
}
$clave = trim((string)fgets(STDIN));
if (stripos(PHP_OS, 'WIN') !== 0) {
    system('stty echo');
}
echo "\n";
if ($m = validar_clave_nueva($clave)) {
    exit($m . "\n");
}
mongo()->selectCollection('usuarios')->insertOne([
    'nombre' => trim($nombre), 'correo' => $correo, 'contrasena' => password_hash($clave, PASSWORD_DEFAULT),
    'rol' => 'administrador', 'createdAt' => nowUTC(), 'origen' => 'consola',
]);
echo "✔ Administrador creado. Inicia sesión en login.php con $correo\n";
