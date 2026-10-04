<?php
/** Elementos comunes de las páginas legales. */

/** Recuadro que explica el carácter académico del proyecto (si está activo en la configuración). */
function aviso_academico(): void
{
    if (!config('academico.activo')) {
        return;
    }
    $datos = array_filter([
        'Institución' => (string)config('academico.institucion', ''),
        'Programa'    => (string)config('academico.programa', ''),
        'Autores'     => (string)config('academico.autores', ''),
        'Año'         => (string)config('academico.anio', ''),
    ]);
    echo '<div class="pendiente-legal mb-4"><p class="mb-1"><i class="bi bi-mortarboard" aria-hidden="true"></i> <strong>Proyecto académico.</strong> '
        . 'CERAMISHOP es un proyecto universitario desarrollado como caso de estudio para ' . e(config('empresa.nombre', 'CERAMICENTRO')) . '. '
        . 'El sitio y la app son una demostración: no se venden productos, no se realizan envíos y el pago PSE es un simulador que no mueve dinero.</p>';
    if ($datos) {
        echo '<p class="mb-0 small">';
        $partes = [];
        foreach ($datos as $k => $v) {
            $partes[] = '<strong>' . e($k) . ':</strong> ' . e($v);
        }
        echo implode(' · ', $partes) . '</p>';
    }
    echo '</div>';
}

/** Dato de la empresa o texto alterno si no está configurado. */
function dato_empresa(string $clave, string $alterno = 'No aplica (proyecto académico)'): string
{
    $v = trim((string)config('empresa.' . $clave, ''));
    return $v !== '' ? $v : $alterno;
}

function fecha_vigencia_legal(): string
{
    return '4 de octubre de 2026';
}
