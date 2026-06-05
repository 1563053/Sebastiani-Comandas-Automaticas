<?php
session_start();
require_once("conexion.php");
header("Content-Type: application/json; charset=utf-8");

if (!isset($_SESSION["usuario"])) {
    http_response_code(403);
    echo json_encode(["success" => false]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$orden_id   = isset($data["orden_id"]) ? (int)$data["orden_id"] : 0;
$id_precio  = isset($data["id_precio"]) ? (int)$data["id_precio"] : 0;
$detalle    = isset($data["detalle"]) ? trim($data["detalle"]) : "";
$precio     = isset($data["precio"]) ? (float)$data["precio"] : 0.0;
$mitad      = !empty($data["mitad"]);
$id_segundo = isset($data["id_segundo_producto"]) && $data["id_segundo_producto"] !== ''
    ? (int)$data["id_segundo_producto"]
    : null;

if ($orden_id <= 0 || $id_precio <= 0) {
    echo json_encode(["success" => false]);
    exit;
}

$conexion->begin_transaction();

try {
    $stmt = $conexion->prepare("
        INSERT INTO detalle_pedido 
        (id_precio, id_orden, impreso, detalle, precio)
        VALUES (?, ?, FALSE, ?, ?)
    ");
    $stmt->bind_param("iisd", $id_precio, $orden_id, $detalle, $precio);
    $stmt->execute();

    $id_pedido = $conexion->insert_id;

    if ($mitad && $id_segundo) {
        $stmt2 = $conexion->prepare("
            INSERT INTO segunda_mitad
            (id_pedido, id_producto)
            VALUES (?, ?)
        ");
        $stmt2->bind_param("ii", $id_pedido, $id_segundo);
        $stmt2->execute();
    }

    $conexion->commit();

    echo json_encode([
        "success" => true,
        "origen" => strtolower($data["origen"] ?? ""),
        "id_retorno" => isset($data["id_retorno"]) ? (int)$data["id_retorno"] : 0
    ]);
} catch (Throwable $e) {
    $conexion->rollback();
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
?>