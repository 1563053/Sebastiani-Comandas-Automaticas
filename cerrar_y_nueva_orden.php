<?php
session_start();
require_once("conexion.php");
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION["usuario"])) {
    echo json_encode(["success" => false]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$idOrden = isset($data["id_orden"]) ? (int)$data["id_orden"] : 0;
$origen = $data["origen"] ?? null;

if ($idOrden <= 0) {
    echo json_encode(["success" => false]);
    exit;
}

$conexion->begin_transaction();

try {
    $stmtMesa = $conexion->prepare("
        SELECT id_mesa
        FROM orden
        WHERE id = ?
        LIMIT 1
    ");
    $stmtMesa->bind_param("i", $idOrden);
    $stmtMesa->execute();
    $resMesa = $stmtMesa->get_result()->fetch_assoc();

    if (!$resMesa) {
        throw new Exception("Orden no encontrada");
    }

    $idMesa = (int)$resMesa["id_mesa"];

    $stmtCheck = $conexion->prepare("
        SELECT COUNT(*) AS total
        FROM detalle_pedido
        WHERE id_orden = ?
    ");
    $stmtCheck->bind_param("i", $idOrden);
    $stmtCheck->execute();
    $resCheck = $stmtCheck->get_result()->fetch_assoc();

    $tienePedidos = ((int)$resCheck["total"]) > 0;

    if ($tienePedidos) {
        $stmtUpdate = $conexion->prepare("
            UPDATE orden
            SET estado = 'pagada',
                fecha_cierre = NOW()
            WHERE id = ?
        ");
        $stmtUpdate->bind_param("i", $idOrden);
        $stmtUpdate->execute();
    } else {
        $stmtDelete = $conexion->prepare("
            DELETE FROM orden
            WHERE id = ?
        ");
        $stmtDelete->bind_param("i", $idOrden);
        $stmtDelete->execute();
    }

    $stmtNueva = $conexion->prepare("
        INSERT INTO orden (id_mesa, estado, subtotal, total, fecha_cierre)
        VALUES (?, 'abierta', 0, 0, NULL)
    ");
    $stmtNueva->bind_param("i", $idMesa);
    $stmtNueva->execute();

    $nuevaOrden = $conexion->insert_id;

    $stmtMesaUpdate = $conexion->prepare("
        UPDATE mesa
        SET estado = 'ocupada'
        WHERE id = ?
    ");
    $stmtMesaUpdate->bind_param("i", $idMesa);
    $stmtMesaUpdate->execute();

    $conexion->commit();

    echo json_encode([
        "success" => true,
        "nueva_orden" => $nuevaOrden,
        "mesa" => $idMesa,
        "origen" => $origen
    ]);
} catch (Throwable $e) {
    $conexion->rollback();
    echo json_encode(["success" => false]);
}