<?php
session_start();
require_once("conexion.php");
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION["usuario"])) {
    echo json_encode(["success" => false]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$id_orden = isset($data["id_orden"]) ? (int)$data["id_orden"] : 0;
$tipo = strtolower(trim($data["tipo"] ?? ""));
$opcion = strtolower(trim($data["opcion"] ?? ""));
$monto = isset($data["monto"]) ? (float)$data["monto"] : 0;

if ($id_orden <= 0 || $tipo === '' || $opcion === '' || $monto <= 0) {
    echo json_encode(["success" => false]);
    exit;
}

$conexion->begin_transaction();

try {
    $stmt = $conexion->prepare("
        INSERT INTO modificacion (id_orden, tipo, opcion, monto)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("issd", $id_orden, $tipo, $opcion, $monto);
    $stmt->execute();

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