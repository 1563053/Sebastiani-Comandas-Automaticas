<?php
require_once("conexion.php");

$data = json_decode(file_get_contents("php://input"), true);

$id_orden = $data["id_orden"] ?? null;

if (!$id_orden) {
    echo json_encode(["success" => false]);
    exit;
}

$stmt = $conexion->prepare("
    SELECT COUNT(*) as total
    FROM detalle_pedido
    WHERE id_orden = ?
    AND impreso = 0
");
$stmt->bind_param("s", $id_orden);
$stmt->execute();

$result = $stmt->get_result()->fetch_assoc();

echo json_encode([
    "success" => true,
    "no_impresos" => intval($result["total"])
]);