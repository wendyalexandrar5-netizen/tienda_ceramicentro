<?php
/**
 * Carga datos de ejemplo en el MongoDB SIMULADO (tests/fake_mongo.php) imitando la estructura
 * real de las colecciones (incluye documentos "antiguos" para probar compatibilidad).
 * Uso: CS_FAKE_DB=/tmp/db.ser php -d auto_prepend_file=tests/fake_mongo.php tests/seed.php
 */
require_once __DIR__ . '/../includes/bootstrap.php';

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

@unlink(\MongoDB\FakeStore::archivo());
\MongoDB\FakeStore::$data = [];
$db = mongo();

$cats = [];
foreach (['Baldosas', 'Enchapes', 'Techos PVC', 'Baños y lavamanos', 'Grifería', 'Iluminación', 'Estucos'] as $c) {
    $cats[$c] = $db->selectCollection('categorias')->insertOne(['nombre' => $c])->getInsertedId();
}
$productos = [
    ['Enchape castillo x50und', 'imagenes/castillo.jpg', 'Enchapes', 58000, 40],
    ['Enchape gres clasico x50und', 'imagenes/clasica.jpg', 'Enchapes', 52000, 25],
    ['Baldosa azulejo en tonos esmeralda x40und', 'imagenes/esmeraldas.jpg', 'Baldosas', 61000, 3],
    ['Enchape espacato matizado x50und', 'imagenes/espacato.jpg', 'Enchapes', 49000, 18],
    ['Enchape hexagonal pietratto x50und', 'imagenes/hexagonal.png', 'Enchapes', 75000, 0],
    ['Baldosa de Marmol x20und', 'imagenes/marmol.jpg', 'Baldosas', 120000, 12],
    ['Baldosa mosaico de piedra x40und', 'imagenes/mosaico.webp', 'Baldosas', 89000, 9],
    ['Techo PVC en relieve blanco', 'imagenes/Techopvc.jpeg', 'Techos PVC', 35000, 60],
    ['Techo PVC tipo madera', 'imagenes/Techopvcmadera.jpg', 'Techos PVC', 39000, 30],
    ['Baño blanco', 'imagenes/Baño.jpg', 'Baños y lavamanos', 420000, 5],
    ['Lavamanos negro', 'imagenes/lavamanosnegro.jpg', 'Baños y lavamanos', 210000, 7],
    ['Regadera circular plateada', 'imagenes/regaderacircularplateada.jpg', 'Grifería', 98000, 15],
    ['Panel de luz redondo', 'imagenes/panelredondo.jpg', 'Iluminación', 27000, 50],
    ['Estuco plastico Supermastick x1.4gln', 'imagenes/supermastick1.4gln.jpg', 'Estucos', 32000, 22],
];
$ids = [];
$t = time() - 86400 * 40;
foreach ($productos as $i => [$n, $img, $cat, $precio, $stock]) {
    $ids[$n] = $db->selectCollection('productos')->insertOne([
        'nombre' => $n, 'descripcion' => 'Producto de calidad de CERAMICENTRO: ' . mb_strtolower($n) . '. Ideal para interiores y exteriores.',
        'precio' => (float)$precio, 'stock' => $stock, 'categoria_id' => $cats[$cat], 'imagen' => $img,
        'createdAt' => new UTCDateTime(($t + $i * 3600) * 1000), 'actualizado_en' => new UTCDateTime(($t + $i * 3600) * 1000),
    ])->getInsertedId();
}
$admin = $db->selectCollection('usuarios')->insertOne([
    'nombre' => 'Admin Ceramicentro', 'correo' => 'admin@ceramicentro.test', 'contrasena' => password_hash('Admin12345', PASSWORD_DEFAULT),
    'rol' => 'administrador', 'createdAt' => new UTCDateTime(),
])->getInsertedId();
// Cliente "antiguo": campo contraseña con ñ, correo con mayúsculas y sin createdAt
$cliente = $db->selectCollection('usuarios')->insertOne([
    'nombre' => 'Laura Gómez', 'correo' => 'Laura@Correo.test', 'contraseña' => password_hash('Cliente123', PASSWORD_DEFAULT), 'rol' => 'cliente',
])->getInsertedId();
$otro = $db->selectCollection('usuarios')->insertOne([
    'nombre' => 'Pedro Ruiz', 'correo' => 'pedro@correo.test', 'contrasena' => password_hash('Pedro12345', PASSWORD_DEFAULT), 'rol' => 'cliente', 'createdAt' => new UTCDateTime(),
])->getInsertedId();

// Pedido antiguo (formato original: estado "Pagado", sin número ni historial)
$p = $db->selectCollection('pedidos')->insertOne([
    'usuario_id' => $cliente, 'fecha' => new UTCDateTime((time() - 86400 * 10) * 1000), 'total' => 116000.0, 'estado' => 'Pagado',
])->getInsertedId();
$db->selectCollection('pedido_detalle')->insertOne([
    'pedido_id' => $p, 'producto_id' => $ids['Enchape castillo x50und'], 'nombre_producto' => 'Enchape castillo x50und',
    'cantidad' => 2, 'precio_unitario' => 58000.0, 'subtotal' => 116000.0,
]);
// Pedido de otro cliente (para probar que no se puede ver ajeno)
$p2 = $db->selectCollection('pedidos')->insertOne([
    'usuario_id' => $otro, 'fecha' => new UTCDateTime((time() - 86400 * 2) * 1000), 'total' => 35000.0, 'estado' => 'Pagado', 'origen' => 'app_android',
])->getInsertedId();
$db->selectCollection('pedido_detalle')->insertOne([
    'pedido_id' => $p2, 'producto_id' => $ids['Techo PVC en relieve blanco'], 'nombre_producto' => 'Techo PVC en relieve blanco',
    'cantidad' => 1, 'precio_unitario' => 35000.0, 'subtotal' => 35000.0,
]);
$db->selectCollection('historial_productos')->insertOne([
    'id_admin' => $admin, 'nombre_admin' => 'Admin Ceramicentro', 'id_producto' => $ids['Baño blanco'], 'producto_nombre' => 'Baño blanco',
    'accion' => 'agrego', 'cambios' => ['Producto agregado', 'Precio inicial: 420000'], 'fecha' => new UTCDateTime((time() - 86400 * 30) * 1000),
]);
file_put_contents(sys_get_temp_dir() . '/cs_ids.json', json_encode([
    'pedido_cliente' => (string)$p, 'pedido_otro' => (string)$p2, 'cliente' => (string)$cliente, 'otro' => (string)$otro,
    'producto_castillo' => (string)$ids['Enchape castillo x50und'], 'producto_esmeralda' => (string)$ids['Baldosa azulejo en tonos esmeralda x40und'],
    'producto_agotado' => (string)$ids['Enchape hexagonal pietratto x50und'], 'cat_enchapes' => (string)$cats['Enchapes'],
]));
echo "Datos de prueba cargados en " . \MongoDB\FakeStore::archivo() . "\n";
