<?php
/**
 * Cancela los pedidos "Pendiente de pago" cuya reserva venció y devuelve el inventario.
 * La tienda ya lo hace sola al navegar; este script permite programarlo
 * (Programador de tareas de Windows o cron), por ejemplo cada hora:
 *   php C:\xampp\htdocs\tiendaonline_mongodb\scripts\liberar_reservas.php
 */
if (PHP_SAPI !== 'cli') {
    exit("Solo se puede ejecutar desde la línea de comandos.\n");
}
require_once __DIR__ . '/../includes/pedidos.php';
$n = liberar_pedidos_vencidos(true);
echo "Pedidos cancelados por reserva vencida: $n\n";
