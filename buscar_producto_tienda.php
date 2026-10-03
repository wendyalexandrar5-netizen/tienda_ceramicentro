<?php
/** Búsqueda de productos (compatibilidad): ahora integrada en la tienda con filtros. */
require_once __DIR__ . '/includes/bootstrap.php';
requerir_sesion('cliente');
redirigir('tienda.php', ['q' => trim((string)($_GET['buscar'] ?? ($_GET['q'] ?? '')))]);
