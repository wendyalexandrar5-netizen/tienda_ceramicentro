<?php
/**
 * Control de acceso por rol (compatibilidad con el código existente).
 * Uso: verificarSesion('administrador') | verificarSesion('cliente') | verificarSesion(['cliente','administrador'])
 */
require_once __DIR__ . '/includes/bootstrap.php';

if (!function_exists('verificarSesion')) {
    function verificarSesion($roles = null): void
    {
        requerir_sesion($roles);
    }
}
