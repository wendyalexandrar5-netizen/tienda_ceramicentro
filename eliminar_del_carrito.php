<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: ver_carrito.php");
    exit;
}

$productoId = $_GET['id'];

if (!isset($_SESSION['carrito'])) {
    header("Location: ver_carrito.php");
    exit;
}

if (isset($_SESSION['carrito'][$productoId])) {
    unset($_SESSION['carrito'][$productoId]);
}

if (empty($_SESSION['carrito'])) {
    unset($_SESSION['carrito']);
}

header("Location: ver_carrito.php");
exit;
?>
