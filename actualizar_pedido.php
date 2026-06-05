<?php
session_start();
require_once("conexion.php");
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION["usuario"])) {
    echo json_encode(["success" => false]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$id_pedido = isset($data["id_pedido"]) ? (int)$data["id_pedido"] : 0;
$id_precio = isset($data["id_precio"]) ? (int)$data["id_precio"] : 0;
$detalle = isset($data["detalle"]) ? trim($data["detalle"]) : '';
$precio = isset($data["precio"]) ? (float)$data["precio"] : 0.0;
$mitad = !empty($data["mitad"]);
$id_segundo = isset($data["id_segundo_producto"]) && $data["id_segundo_producto"] !== ''
    ? (int)$data["id_segundo_producto"]
    : null;
$impreso = !empty($data["impreso"]) ? 1 : 0;

if ($id_pedido <= 0 || $id_precio <= 0) {
    echo json_encode(["success" => false]);
    exit;
}

$conexion->begin_transaction();

try {
    $stmtOrden = $conexion->prepare("
        SELECT id_orden
        FROM detalle_pedido
        WHERE id = ?
        LIMIT 1
    ");
    $stmtOrden->bind_param("i", $id_pedido);
    $stmtOrden->execute();
    $resOrden = $stmtOrden->get_result()->fetch_assoc();

    if (!$resOrden) {
        throw new Exception("Pedido no encontrado");
    }

    $id_orden = (int)$resOrden['id_orden'];

    $stmt = $conexion->prepare("
        UPDATE detalle_pedido
        SET id_precio = ?, detalle = ?, precio = ?, impreso = ?
        WHERE id = ?
    ");
    $stmt->bind_param("isdid", $id_precio, $detalle, $precio, $impreso, $id_pedido);
    $stmt->execute();

    $stmtDelete = $conexion->prepare("
        DELETE FROM segunda_mitad
        WHERE id_pedido = ?
    ");
    $stmtDelete->bind_param("i", $id_pedido);
    $stmtDelete->execute();

    if ($mitad && $id_segundo) {
        $stmt2 = $conexion->prepare("
            INSERT INTO segunda_mitad (id_pedido, id_producto)
            VALUES (?, ?)
        ");
        $stmt2->bind_param("ii", $id_pedido, $id_segundo);
        $stmt2->execute();
    }

    $stmtSubtotal = $conexion->prepare("
        SELECT IFNULL(SUM(precio), 0) AS subtotal
        FROM detalle_pedido
        WHERE id_orden = ?
    ");
    $stmtSubtotal->bind_param("i", $id_orden);
    $stmtSubtotal->execute();
    $resSubtotal = $stmtSubtotal->get_result()->fetch_assoc();

    $subtotal = (float)($resSubtotal['subtotal'] ?? 0);
    $total = $subtotal;

    $stmtMods = $conexion->prepare("
        SELECT tipo, opcion, monto
        FROM modificacion
        WHERE id_orden = ?
        ORDER BY id
    ");
    $stmtMods->bind_param("i", $id_orden);
    $stmtMods->execute();
    $resMods = $stmtMods->get_result();

    while ($mod = $resMods->fetch_assoc()) {
        if ($mod['opcion'] === 'porcentaje') {
            $montoCalculado = ($subtotal * (float)$mod['monto']) / 100;
        } else {
            $montoCalculado = (float)$mod['monto'];
        }

        if ($mod['tipo'] === 'descuento') {
            $montoCalculado *= -1;
        }

        $total += $montoCalculado;
    }

    $stmtUpdate = $conexion->prepare("
        UPDATE orden
        SET subtotal = ?, total = ?
        WHERE id = ?
    ");
    $stmtUpdate->bind_param("ddi", $subtotal, $total, $id_orden);
    $stmtUpdate->execute();

    $conexion->commit();

    echo json_encode(["success" => true]);
} catch (Throwable $e) {
    $conexion->rollback();
    echo json_encode(["success" => false]);
}
?>