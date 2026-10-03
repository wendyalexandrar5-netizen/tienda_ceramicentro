<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function verificarSesion($roles = null) {
    if (!isset($_SESSION['usuario'])) {
        header("Location: login.php");
        exit();
    }

    if ($roles === null) return;

    $rolUsuario = $_SESSION['usuario']['rol'] ?? null;

    if (is_string($roles)) {
        if ($rolUsuario !== $roles) {
            header("Location: acceso_denegado.php");
            exit();
        }
        return;
    }

    if (is_array($roles)) {
        if (!in_array($rolUsuario, $roles, true)) {
            header("Location: acceso_denegado.php");
            exit();
        }
        return;
    }

    header("Location: acceso_denegado.php");
    exit();
}
