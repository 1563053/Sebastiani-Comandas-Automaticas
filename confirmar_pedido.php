<?php
session_start();
require_once("conexion.php");

$data = json_decode(file_get_contents("php://input"), true);
$idOrden = $data["id_orden"] ?? null;

if (!$idOrden) {
    echo json_encode(["success"=>false]);
    exit;
}

$stmt = $conexion->prepare("
    UPDATE detalle_pedido
    SET impreso = 1
    WHERE id_orden = ?
");
$stmt->bind_param("s", $idOrden);
$stmt->execute();

echo json_encode(["success"=>true]);