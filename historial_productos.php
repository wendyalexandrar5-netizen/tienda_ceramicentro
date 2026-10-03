<?php
include("verificar_acceso.php");
verificarSesion('administrador');

require __DIR__ . '/vendor/autoload.php';
include("conexion_mongo.php");

use MongoDB\BSON\UTCDateTime;

date_default_timezone_set("America/Bogota");

$db = mongo();
$colHist = $db->selectCollection('historial_productos');

$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';

$filter = [];

if (!empty($fecha_desde) && !empty($fecha_hasta)) {

    $tz = new DateTimeZone("America/Bogota");

    $desde = new DateTime(
        $fecha_desde . ' 00:00:00',
        $tz
    );

    $hasta = new DateTime(
        $fecha_hasta . ' 23:59:59',
        $tz
    );

    $filter['fecha'] = [
        '$gte' => new UTCDateTime(
            $desde->getTimestamp() * 1000
        ),
        '$lte' => new UTCDateTime(
            $hasta->getTimestamp() * 1000
        )
    ];
}

$cursor = $colHist->find(
    $filter,
    [
        'sort'    => ['fecha' => -1],
        'typeMap' => [
            'root'=>'array',
            'document'=>'array',
            'array'=>'array'
        ]
    ]
);

$filas = iterator_to_array($cursor, false);
?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <title>
        Historial de Cambios - CERAMICENTRO
    </title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css"
        rel="stylesheet"
    >

    <style>

        body{
            background-color:#f9fafb;
        }

        .header{
            background:#c62828;
            color:white;
            padding:20px;
            text-align:center;
        }

        .btn-volver{
            background:#e53935;
            color:white;
            border:none;
            padding:10px 15px;
            border-radius:10px;
        }

        .btn-volver:hover{
            background:#b71c1c;
        }

        .accion-agrego{
            background:#4caf50;
            color:white;
            padding:3px 8px;
            border-radius:5px;
        }

        .accion-edito{
            background:#ff9800;
            color:white;
            padding:3px 8px;
            border-radius:5px;
        }

        .accion-elimino{
            background:#f44336;
            color:white;
            padding:3px 8px;
            border-radius:5px;
        }

        pre{
            white-space:pre-wrap;
            font-size:.9rem;
        }

    </style>

</head>

<body>

<div class="header mb-4">

    <h1>
        <i class="bi bi-clock-history"></i>
        Historial de Productos
    </h1>

</div>

<div class="container">

    <form method="GET" class="row g-3 mb-4">

        <div class="col-md-4">

            <label class="form-label">
                Desde:
            </label>

            <input
                type="date"
                class="form-control"
                name="fecha_desde"
                value="<?= htmlspecialchars($fecha_desde) ?>"
            >

        </div>

        <div class="col-md-4">

            <label class="form-label">
                Hasta:
            </label>

            <input
                type="date"
                class="form-control"
                name="fecha_hasta"
                value="<?= htmlspecialchars($fecha_hasta) ?>"
            >

        </div>

        <div class="col-md-4 d-flex align-items-end gap-2">

            <button class="btn btn-primary">

                <i class="bi bi-filter"></i>

                Filtrar

            </button>

            <a
                href="historial_productos.php"
                class="btn btn-outline-secondary"
            >

                <i class="bi bi-x-circle"></i>

                Limpiar

            </a>

        </div>

    </form>

    <label class="form-label">
        <strong>Buscar:</strong>
    </label>

    <input
        type="text"
        id="buscar"
        class="form-control mb-3"
        placeholder="Buscar en todos los campos..."
    >

    <div class="table-responsive">

        <table
            class="table table-bordered table-hover shadow-sm"
            id="tablaHistorial"
        >

            <thead class="table-light">

                <tr>

                    <th>Usuario</th>

                    <th>Acción</th>

                    <th>Producto</th>

                    <th>Categoría Antes</th>

                    <th>Categoría Después</th>

                    <th>Cambios</th>

                    <th>Fecha</th>

                </tr>

            </thead>

            <tbody>

                <?php foreach ($filas as $row):

                    $accion = strtolower(
                        $row['accion'] ?? ''
                    );

                    $accion_class = match($accion) {

                        'agrego'  => 'accion-agrego',

                        'edito'   => 'accion-edito',

                        'elimino' => 'accion-elimino',

                        default   => ''
                    };

                    $cambios =
                    $row['cambios'] ?? 'N/A';

                    if (is_array($cambios)) {

                        $cambios = implode(
                            "\n• ",
                            array_map(
                                'htmlspecialchars',
                                $cambios
                            )
                        );

                        $cambios =
                        "• " . $cambios;

                    } else {

                        $cambios =
                        htmlspecialchars($cambios);
                    }

                    $categoriaAntes =
                    $row["categoria_anterior"] ?? 'N/A';

                    $categoriaDesp =
                    $row["categoria_nueva"] ?? 'N/A';

                    if ($row['fecha'] ?? null) {

                        $fechaObj =
                        $row['fecha']->toDateTime();

                        $fechaObj->setTimezone(
                            new DateTimeZone(
                                "America/Bogota"
                            )
                        );

                        $fecha =
                        $fechaObj->format(
                            'Y-m-d H:i:s'
                        );

                    } else {

                        $fecha =
                        'Fecha no disponible';
                    }

                ?>

                <tr>

                    <td>
                        <?= htmlspecialchars(
                            $row['nombre_admin']
                            ?? 'Desconocido'
                        ) ?>
                    </td>

                    <td>

                        <span class="<?= $accion_class ?>">

                            <?= ucfirst($accion) ?>

                        </span>

                    </td>

                    <td>

                        <?= htmlspecialchars(
                            $row['producto_nombre']
                            ?? 'N/A'
                        ) ?>

                    </td>

                    <td>

                        <?= htmlspecialchars(
                            $categoriaAntes
                        ) ?>

                    </td>

                    <td>

                        <?= htmlspecialchars(
                            $categoriaDesp
                        ) ?>

                    </td>

                    <td>

                        <pre><?= $cambios ?></pre>

                    </td>

                    <td>

                        <?= $fecha ?>

                    </td>

                </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    </div>

    <a
        href="exportar_historial_excel.php"
        class="btn btn-success mt-3"
    >

        <i class="bi bi-file-earmark-excel"></i>

        Exportar a Excel

    </a>

    <br><br>

    <a
        href="panel_admin.php"
        class="btn btn-volver"
    >

        <i class="bi bi-arrow-left-circle"></i>

        Volver

    </a>

</div>

<script>

document
.getElementById("buscar")
.addEventListener("keyup", function () {

    const valor =
    this.value.toLowerCase();

    const filas =
    document.querySelectorAll(
        "#tablaHistorial tbody tr"
    );

    filas.forEach(f => {

        f.style.display =
        f.innerText
        .toLowerCase()
        .includes(valor)

        ? ""

        : "none";
    });
});

</script>

</body>
</html>
