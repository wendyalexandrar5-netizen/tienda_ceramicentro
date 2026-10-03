<?php
/** Aviso visible para secciones legales que CERAMICENTRO debe completar o revisar. */
function pendiente_legal(string $texto): void
{
    echo '<p class="pendiente-legal"><i class="bi bi-pencil-square" aria-hidden="true"></i> <strong>[PENDIENTE – CERAMICENTRO]</strong> ' . e($texto) . '</p>';
}
