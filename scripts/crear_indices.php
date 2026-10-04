<?php
/**
 * Crea los índices de MongoDB que aceleran las consultas de la tienda.
 * Es seguro ejecutarlo varias veces y NO modifica ni borra documentos.
 *
 * Uso (desde la carpeta del proyecto):   php scripts/crear_indices.php
 */
if (PHP_SAPI !== 'cli') {
    exit("Solo se puede ejecutar desde la línea de comandos.\n");
}
require_once __DIR__ . '/../includes/bootstrap.php';

$db = mongo();
$indices = [
    'productos'           => [[['categoria_id' => 1, 'nombre' => 1], []], [['nombre' => 1], []], [['stock' => 1], []]],
    'categorias'          => [[['nombre' => 1], []]],
    'pedidos'             => [[['usuario_id' => 1, 'fecha' => -1], []], [['fecha' => -1], []], [['estado' => 1, 'fecha' => -1], []], [['numero' => 1], ['sparse' => true]],
                              [['usuario_id' => 1, 'token_cliente' => 1], ['sparse' => true]]],
    'pedido_detalle'      => [[['pedido_id' => 1], []], [['producto_id' => 1], []]],
    'historial_productos' => [[['fecha' => -1], []], [['id_producto' => 1], []]],
    'intentos_login'      => [[['clave' => 1, 'fecha' => -1], []], [['fecha' => 1], ['expireAfterSeconds' => 86400]]],
    'tokens_app'          => [[['token_hash' => 1], ['unique' => true]], [['usuario_id' => 1], []], [['expira' => 1], ['expireAfterSeconds' => 0]]],
    'mensajes_contacto'   => [[['fecha' => -1], []]],
    'recuperaciones_clave'=> [[['token_hash' => 1], ['unique' => true]], [['expira' => 1], ['expireAfterSeconds' => 0]]],
];

foreach ($indices as $coleccion => $lista) {
    foreach ($lista as [$claves, $opciones]) {
        try {
            $nombre = $db->selectCollection($coleccion)->createIndex($claves, $opciones);
            echo "✔ $coleccion: $nombre\n";
        } catch (Throwable $e) {
            echo "✘ $coleccion: " . $e->getMessage() . "\n";
        }
    }
}

// Correos únicos (sin distinguir mayúsculas). Solo se crea si no hay duplicados existentes.
$duplicados = $db->selectCollection('usuarios')->aggregate([
    ['$group' => ['_id' => ['$toLower' => '$correo'], 'n' => ['$sum' => 1]]],
    ['$match' => ['n' => ['$gt' => 1]]],
])->toArray();
if ($duplicados) {
    echo "! usuarios: hay correos repetidos, no se creó el índice único. Revise:\n";
    foreach ($duplicados as $d) {
        echo "    - {$d['_id']} ({$d['n']} cuentas)\n";
    }
    $db->selectCollection('usuarios')->createIndex(['correo' => 1]);
} else {
    try {
        $db->selectCollection('usuarios')->createIndex(['correo' => 1], ['unique' => true, 'collation' => ['locale' => 'es', 'strength' => 2], 'name' => 'correo_unico']);
        echo "✔ usuarios: correo_unico\n";
    } catch (Throwable $e) {
        echo "✘ usuarios: " . $e->getMessage() . "\n";
    }
}
echo "Listo.\n";
