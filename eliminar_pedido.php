<?php
session_start();
require_once("conexion.php");
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION["usuario"])) {
    echo json_encode(["success" => false]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$id = isset($data['id']) ? (int)$data['id'] : 0;

if ($id <= 0) {
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
    $stmtOrden->bind_param("i", $id);
    $stmtOrden->execute();
    $resOrden = $stmtOrden->get_result()->fetch_assoc();

    if (!$resOrden) {
        throw new Exception("Pedido no encontrado");
    }

    $idOrden = (int)$resOrden['id_orden'];

    $stmtSegunda = $conexion->prepare("
        DELETE FROM segunda_mitad
        WHERE id_pedido = ?
    ");
    $stmtSegunda->bind_param("i", $id);
    $stmtSegunda->execute();

    $stmt = $conexion->prepare("
        DELETE FROM detalle_pedido
        WHERE id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $stmtSubtotal = $conexion->prepare("
        SELECT IFNULL(SUM(precio), 0) AS subtotal
        FROM detalle_pedido
        WHERE id_orden = ?
    ");
    $stmtSubtotal->bind_param("i", $idOrden);
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
    $stmtMods->bind_param("i", $idOrden);
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
    $stmtUpdate->bind_param("ddi", $subtotal, $total, $idOrden);
    $stmtUpdate->execute();

    $conexion->commit();

    echo json_encode(["success" => true]);
} catch (Throwable $e) {
    $conexion->rollback();
    echo json_encode(["success" => false]);
}