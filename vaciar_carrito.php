<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

unset($_SESSION['carrito']);

header("Location: ver_carrito.php");
exit;
?>
