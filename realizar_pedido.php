<?php
session_start();

require __DIR__ . "/vendor/autoload.php";
include("verificar_acceso.php");
verificarSesion("cliente");
include("conexion_mongo.php");

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

date_default_timezone_set("America/Bogota");

if (!isset($_SESSION['usuario']) || empty($_SESSION['carrito'])) {
    header("Location: tienda.php");
    exit;
}

$usuario = $_SESSION['usuario'];
$carrito = $_SESSION['carrito'];

$db = mongo();
$colProductos     = $db->selectCollection("productos");
$colPedidos       = $db->selectCollection("pedidos");
$colPedidoDetalle = $db->selectCollection("pedido_detalle");

$usuarioId = $usuario["id"];

foreach ($carrito as $productoId => $item) {

    if (!preg_match('/^[a-f\d]{24}$/i', $productoId)) {
        header("Location: ver_carrito.php?error=ID inválido");
        exit;
    }

    $producto = $colProductos->findOne(
        ["_id" => new ObjectId($productoId)],
        ["typeMap" => ["root" => "array", "document" => "array"]]
    );

    if (!$producto) {
        header("Location: ver_carrito.php?error=Producto no encontrado");
        exit;
    }

    if ($item["cantidad"] > $producto["stock"]) {
        header("Location: ver_carrito.php?error=stock&id=$productoId");
        exit;
    }
}

$total = 0;

foreach ($carrito as $item) {
    $total += $item["precio"] * $item["cantidad"];
}

$fechaBogota = new DateTime("now", new DateTimeZone("America/Bogota"));

$pedidoInsert = $colPedidos->insertOne([
    "usuario_id" => new ObjectId($usuarioId),
    "fecha"      => new UTCDateTime(),
    "total"      => (float)$total,
    "estado"     => "Pagado"
]);

$pedidoId = $pedidoInsert->getInsertedId();

foreach ($carrito as $productoId => $item) {

    $colPedidoDetalle->insertOne([
        "pedido_id"       => $pedidoId,
        "producto_id"     => new ObjectId($productoId),
        "nombre_producto" => $item["nombre"],
        "cantidad"        => (int)$item["cantidad"],
        "precio_unitario" => (float)$item["precio"],
        "subtotal"        => (float)($item["precio"] * $item["cantidad"])
    ]);

    $colProductos->updateOne(
        ["_id" => new ObjectId($productoId)],
        ['$inc' => ["stock" => -$item["cantidad"]]]
    );
}

$_SESSION["ultimo_pedido"] = [
    "id"        => (string)$pedidoId,
    "fecha"     => $fechaBogota->format("Y-m-d H:i:s"),
    "total"     => $total,
    "productos" => $carrito
];

unset($_SESSION["carrito"]);

echo "
<html>
<head>
<meta charset='UTF-8'>
<title>Redirigiendo a PSE...</title>
</head>

<body style='display:flex; align-items:center; justify-content:center; height:100vh; flex-direction:column; font-family:sans-serif; text-align:center;'>

<h2>Redirigiéndote a PSE...</h2>
<p>Espera unos segundos mientras procesamos tu pago.</p>

<script>
    window.open('https://registro.pse.com.co/PSEUserRegister/', '_blank');

    setTimeout(() => {
        window.location.href = 'pago_exitoso.php';
    }, 8000);
</script>

</body>
</html>
";
exit;
?>
