<?php
/** Indicador de pasos del proceso de compra. $pasoActual: 1..4 */
function pasos_compra(int $pasoActual): void
{
    $pasos = ['Carrito', 'Confirmar pedido', 'Pago PSE (simulado)', 'Confirmación'];
    echo '<ol class="pasos-compra" aria-label="Pasos de la compra">';
    foreach ($pasos as $i => $p) {
        $n = $i + 1;
        $cls = $n < $pasoActual ? 'hecho' : ($n === $pasoActual ? 'actual' : '');
        echo '<li class="' . $cls . '"' . ($n === $pasoActual ? ' aria-current="step"' : '') . '>' . e($p) . '</li>';
    }
    echo '</ol>';
}
