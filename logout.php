<?php
/** Cierra la sesión (web) de forma segura y vuelve al inicio de sesión. */
require_once __DIR__ . '/includes/bootstrap.php';

sesion_cerrar();
iniciar_sesion();
flash('success', 'Cerraste sesión correctamente. ¡Vuelve pronto!');
redirigir('login.php');
