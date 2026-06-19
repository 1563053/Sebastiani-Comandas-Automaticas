<?php
session_start();
require_once("conexion.php");
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION["usuario"])) {
    echo json_encode(["success" => false]);
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    echo json_encode(["success" => false]);
    exit;
}

$stmt = $conexion->prepare("
    SELECT 
        dped.id,
        dped.detalle,
        dped.precio,
        dped.cantidad,
        dped.id_precio,
        dped.impreso,
        dpre.nombre AS tamano,
        dpre.id_producto,
        prod.nombre AS producto,
        prod.descripcion,
        prod.categoria,
        sm.id_producto AS id_segundo_producto
    FROM detalle_pedido dped
    INNER JOIN detalle_precio dpre 
        ON dped.id_precio = dpre.id
    INNER JOIN producto prod
        ON dpre.id_producto = prod.id
    LEFT JOIN segunda_mitad sm
        ON sm.id_pedido = dped.id
    WHERE dped.id = ?
    LIMIT 1
");

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo json_encode(["success" => false]);
    exit;
}

$data = $result->fetch_assoc();

echo json_encode([
    "success" => true,
    "data" => $data
]);
?>
