<?php
session_start();
require_once("conexion.php");
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION["usuario"])) {
    echo json_encode(["success" => false]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$idOrden = isset($data['id_orden']) ? (int)$data['id_orden'] : 0;
$nuevaMesa = isset($data['mesa']) ? (int)$data['mesa'] : 0;

if ($idOrden <= 0 || $nuevaMesa <= 0) {
    echo json_encode(["success" => false]);
    exit;
}

$conexion->begin_transaction();

try {
    $stmt = $conexion->prepare("
        SELECT id_mesa
        FROM orden
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $idOrden);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    if (!$res) {
        throw new Exception("Orden no encontrada");
    }

    $mesaActual = (int)$res['id_mesa'];

    if ($mesaActual !== $nuevaMesa) {
        $stmt = $conexion->prepare("
            UPDATE orden
            SET id_mesa = ?
            WHERE id = ?
        ");
        $stmt->bind_param("ii", $nuevaMesa, $idOrden);
        $stmt->execute();

        $stmt = $conexion->prepare("
            UPDATE mesa
            SET estado = 'libre'
            WHERE id = ?
        ");
        $stmt->bind_param("i", $mesaActual);
        $stmt->execute();

        $stmt = $conexion->prepare("
            UPDATE mesa
            SET estado = 'ocupada'
            WHERE id = ?
        ");
        $stmt->bind_param("i", $nuevaMesa);
        $stmt->execute();
    }

    $conexion->commit();
    echo json_encode(["success" => true]);
} catch (Throwable $e) {
    $conexion->rollback();
    echo json_encode(["success" => false]);
}