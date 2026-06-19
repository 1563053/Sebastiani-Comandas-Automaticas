<?php
session_start();
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION["usuario"])) {
    header("Location: index.php");
    exit;
}

require_once("conexion.php");

function responderJson(array $data): void {
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($data);
    exit;
}

function textoMesa(int $id): string {
    return $id === 0 ? "Delivery" : "Mesa " . $id;
}

$rawInput = file_get_contents("php://input");
$jsonInput = json_decode($rawInput, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_array($jsonInput)) {
    $accion = $jsonInput['accion'] ?? '';

    if ($accion === 'reabrir') {
        $idOrden = (int)($jsonInput['id_orden'] ?? 0);

        if ($idOrden <= 0) {
            responderJson(["success" => false, "error" => "ID inválido"]);
        }

        $stmt = $conexion->prepare("
            SELECT id_mesa
            FROM orden
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $idOrden);
        $stmt->execute();
        $orden = $stmt->get_result()->fetch_assoc();

        if (!$orden) {
            responderJson(["success" => false, "error" => "Orden no encontrada"]);
        }

        $stmtUpdate = $conexion->prepare("
            UPDATE orden
            SET estado = 'abierta', fecha_cierre = NULL
            WHERE id = ?
        ");
        $stmtUpdate->bind_param("i", $idOrden);
        $stmtUpdate->execute();

        $stmtMesa = $conexion->prepare("
            UPDATE mesa
            SET estado = 'ocupada'
            WHERE id = ?
        ");
        $stmtMesa->bind_param("i", $orden['id_mesa']);
        $stmtMesa->execute();

        responderJson(["success" => true]);
    }

    if ($accion === 'pagar') {
        $idOrden = (int)($jsonInput['id_orden'] ?? 0);

        if ($idOrden <= 0) {
            responderJson(["success" => false, "error" => "ID inválido"]);
        }

        $stmt = $conexion->prepare("
            UPDATE orden
            SET estado = 'pagada', fecha_cierre = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("i", $idOrden);
        $stmt->execute();

        responderJson(["success" => true]);
    }
}

$origen = strtolower($_GET['origen'] ?? ($_POST['origen'] ?? ''));
$idPresente = isset($_GET['id']) || isset($_POST['id']);
$idRecibido = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);

if ($origen !== 'caja' && $origen !== 'mesa') {
    header("Location: mesas.php");
    exit;
}

if (!$idPresente || ($origen === 'caja' && $idRecibido <= 0) || ($origen === 'mesa' && $idRecibido < 0)) {
    header("Location: " . ($origen === 'caja' ? "caja.php" : "mesas.php"));
    exit;
}

$salidaPorCerrar = ($origen === 'caja') ? "caja.php" : "mesas.php";

$ordenActual = null;
$estadoOrden = null;
$mesa_id = 0;
$modoSoloMesa = false;

$detalles = [];
$modificaciones = [];
$subtotal = 0;
$total = 0;
$mesasLibres = [];
$productos = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_orden'])) {

    if ($origen !== 'mesa' || $idRecibido < 0) {
        header("Location: mesas.php");
        exit;
    }

    $mesa_id = $idRecibido;

    $stmtVer = $conexion->prepare("
        SELECT id
        FROM orden
        WHERE id_mesa = ? AND estado = 'abierta'
        LIMIT 1
    ");
    $stmtVer->bind_param("i", $mesa_id);
    $stmtVer->execute();
    $resVer = $stmtVer->get_result()->fetch_assoc();

    if ($resVer) {
        header("Location: orden.php?origen=mesa&id=" . urlencode($mesa_id));
        exit;
    }

    $stmtInsert = $conexion->prepare("
        INSERT INTO orden (id_mesa, estado, subtotal, total, fecha_cierre)
        VALUES (?, 'abierta', 0, 0, NULL)
    ");
    $stmtInsert->bind_param("i", $mesa_id);
    $stmtInsert->execute();

    $stmtMesaUpdate = $conexion->prepare("
        UPDATE mesa
        SET estado = 'ocupada'
        WHERE id = ?
    ");
    $stmtMesaUpdate->bind_param("i", $mesa_id);
    $stmtMesaUpdate->execute();

    header("Location: orden.php?origen=mesa&id=" . urlencode($mesa_id));
    exit;
}

if ($origen === 'caja') {

    $stmtOrden = $conexion->prepare("
        SELECT id_mesa, estado
        FROM orden
        WHERE id = ?
        LIMIT 1
    ");
    $stmtOrden->bind_param("i", $idRecibido);
    $stmtOrden->execute();
    $resOrden = $stmtOrden->get_result()->fetch_assoc();

    if (!$resOrden) {
        header("Location: caja.php");
        exit;
    }

    $ordenActual = $idRecibido;
    $mesa_id = (int)$resOrden['id_mesa'];
    $estadoOrden = $resOrden['estado'];
    $modoSoloMesa = false;
}

if ($origen === 'mesa') {

    $mesa_id = $idRecibido;

    $stmtOrdenMesa = $conexion->prepare("
        SELECT id, estado
        FROM orden
        WHERE id_mesa = ? AND estado = 'abierta'
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmtOrdenMesa->bind_param("i", $mesa_id);
    $stmtOrdenMesa->execute();
    $resOrdenMesa = $stmtOrdenMesa->get_result()->fetch_assoc();

    if ($resOrdenMesa) {
        $ordenActual = (int)$resOrdenMesa['id'];
        $estadoOrden = $resOrdenMesa['estado'];
        $modoSoloMesa = false;
    } else {
        $modoSoloMesa = true;
    }
}

$tieneMesa = ($origen === 'mesa');
$puedeAcciones = (!$modoSoloMesa && $ordenActual && $estadoOrden === 'abierta');
$puedeReabrir  = (!$modoSoloMesa && $ordenActual && in_array($estadoOrden, ['cancelada', 'pagada'], true));

/* =========================
   CARGAR DETALLES Y TOTALES
========================= */
if (!$modoSoloMesa && $ordenActual) {

    $stmtDetalle = $conexion->prepare("
        SELECT 
            dped.id AS id_pedido,
            dped.detalle,
            dped.precio AS precio_pedido,
            dped.cantidad,
            dped.id_precio,
            dped.impreso,

            dpre.id_producto,
            dpre.nombre AS nombre_tamano,
            dpre.precio AS precio_tamano,

            prod.nombre AS nombre_producto,
            prod.categoria,

            sm.id_producto AS id_segundo_producto,
            prod2.nombre AS nombre_segunda_mitad,

            dp2.precio AS precio_segunda_mitad

        FROM detalle_pedido dped
        INNER JOIN detalle_precio dpre 
            ON dped.id_precio = dpre.id
        INNER JOIN producto prod
            ON dpre.id_producto = prod.id
        LEFT JOIN segunda_mitad sm
            ON sm.id_pedido = dped.id
        LEFT JOIN producto prod2
            ON sm.id_producto = prod2.id
        LEFT JOIN detalle_precio dp2
            ON dp2.id_producto = prod2.id
            AND dp2.nombre = dpre.nombre
        WHERE dped.id_orden = ?
        ORDER BY dped.id ASC
    ");
    $stmtDetalle->bind_param("i", $ordenActual);
    $stmtDetalle->execute();
    $resultDetalle = $stmtDetalle->get_result();

    while ($row = $resultDetalle->fetch_assoc()) {
        $detalles[] = $row;
    }

    $stmtSubtotal = $conexion->prepare("
        SELECT IFNULL(SUM(precio), 0) AS subtotal
        FROM detalle_pedido
        WHERE id_orden = ?
    ");
    $stmtSubtotal->bind_param("i", $ordenActual);
    $stmtSubtotal->execute();
    $resSubtotal = $stmtSubtotal->get_result()->fetch_assoc();
    $subtotal = (float)($resSubtotal['subtotal'] ?? 0);

    $total = $subtotal;

    $stmtMods = $conexion->prepare("
        SELECT *
        FROM modificacion
        WHERE id_orden = ?
        ORDER BY id
    ");
    $stmtMods->bind_param("i", $ordenActual);
    $stmtMods->execute();
    $resMods = $stmtMods->get_result();

    while ($mod = $resMods->fetch_assoc()) {
        $montoCalculado = 0;

        if ($mod['opcion'] === 'porcentaje') {
            $montoCalculado = ($subtotal * $mod['monto']) / 100;
        } else {
            $montoCalculado = (float)$mod['monto'];
        }

        if ($mod['tipo'] === 'descuento') {
            $montoCalculado *= -1;
        }

        $total += $montoCalculado;

        $modificaciones[] = [
            "id" => $mod['id'],
            "tipo" => ucfirst($mod['tipo']),
            "opcion" => ucfirst($mod['opcion']),
            "monto_original" => $mod['monto'],
            "monto_calculado" => $montoCalculado
        ];
    }

    if ($estadoOrden === 'abierta') {
        $stmtUpdateTotal = $conexion->prepare("
            UPDATE orden
            SET subtotal = ?, total = ?
            WHERE id = ?
        ");
        $stmtUpdateTotal->bind_param("ddi", $subtotal, $total, $ordenActual);
        $stmtUpdateTotal->execute();
    }

    if ($puedeAcciones) {
        $stmtMesas = $conexion->query("
            SELECT id
            FROM mesa
            WHERE estado = 'libre'
            ORDER BY id
        ");
        $mesasLibres = $stmtMesas->fetch_all(MYSQLI_ASSOC);

        $sqlProductos = "
            SELECT 
                p.id,
                p.nombre,
                p.descripcion,
                p.categoria,
                dp.id AS id_precio,
                dp.nombre AS tamano,
                dp.precio
            FROM producto p
            JOIN detalle_precio dp ON dp.id_producto = p.id
            ORDER BY p.id, dp.precio
        ";
        $resProductos = $conexion->query($sqlProductos);

        while ($row = $resProductos->fetch_assoc()) {
            $id = $row['id'];

            if (!isset($productos[$id])) {
                $productos[$id] = [
                    "nombre" => $row["nombre"],
                    "descripcion" => $row["descripcion"],
                    "categoria" => $row["categoria"],
                    "precios" => []
                ];
            }

            $productos[$id]["precios"][] = [
                "id" => $row["id_precio"],
                "tamano" => $row["tamano"],
                "precio" => $row["precio"]
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orden - Sebatiani POS</title>
    <link rel="stylesheet" href="src/output.css">
    <link rel="stylesheet" href="src/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700;800&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="flex-1 flex flex-col h-screen w-screen overflow-hidden text-sm lg:text-base">
    <main class="flex-1 flex flex-col h-full overflow-hidden w-full bg-white relative">
        <div class="flex flex-row items-center justify-between p-4 py-3 border-b border-gray-100 bg-white">
            <div class="flex flex-col justify-between items-center mb-1">
                <div class="flex items-center gap-2">
                    <div class="w-2 h-2 rounded-full bg-[#556B2F] animate-pulse"></div>
                    <span class="font-head font-bold text-base text-[#2C2C2C]">
                        <?php echo htmlspecialchars(textoMesa((int)$mesa_id)); ?>
                    </span>
                </div>

                <?php if (!$modoSoloMesa && $ordenActual): ?>
                <span class="text-xs text-gray-400 font-bold bg-gray-100 px-2 py-1 rounded-lg">
                    Orden <?php echo intval($ordenActual); ?>
                </span>
                <?php endif; ?>
            </div>

            <a href="<?php echo htmlspecialchars($salidaPorCerrar); ?>"
               class="w-10 h-10 text-2xl rounded-full bg-white shadow-sm flex items-center justify-center text-[#A83232] hover:bg-[#A83232] hover:text-white transition-colors">
                <i class="fa-solid fa-xmark"></i>
            </a>
        </div>

        <div class="flex-1 overflow-y-auto p-2 md:p-4 space-y-3">
            <?php if ($modoSoloMesa): ?>
                <form method="POST">
                    <button type="submit" name="crear_orden"
                        class="w-full py-4 rounded-full bg-[#A83232] text-white font-head font-extrabold text-lg shadow-lg hover:shadow-xl hover:-translate-y-1 transition-all flex items-center justify-center gap-3">
                        <span>NUEVO PEDIDO</span>
                        <i class="fa-solid fa-plus"></i>
                    </button>
                </form>
            <?php else: ?>
                <?php
                $contador = 1;
                $mostrarSeparadorAdicional = ($estadoOrden === 'abierta');
                $mostroSeparador = false;
                
                foreach ($detalles as $item):
                    if ($mostrarSeparadorAdicional && !$mostroSeparador && (int)$item['impreso'] === 0) {
                        echo '
                        <div class="flex items-center my-2 md:my-4 text-gray-400 text-xs font-bold tracking-widest">
                            <div class="flex-1 border-t border-gray-300"></div>
                            <span class="px-3">ADICIONAL</span>
                            <div class="flex-1 border-t border-gray-300"></div>
                        </div>
                        ';
                        $mostroSeparador = true;
                    }
                ?>

                <?php if ($item['categoria'] === 'pizza' && !empty($item['nombre_segunda_mitad'])): ?>
                <div class="pedido-item select-none flex gap-3 items-start p-3 md:p-4 rounded-2xl border border-gray-200 group" data-id="<?= $item['id_pedido'] ?>">

                    <div class="w-6 h-6 rounded bg-[#A83232]/10 text-[#A83232] flex items-center justify-center font-bold text-xs mt-1">
                        <?php echo $contador++; ?>
                    </div>

                    <div class="flex-1">

                        <div class="flex items-start gap-3">
                            <span class="flex-1 min-w-0 font-head font-bold text-gray-800 leading-snug break-words">
                                <?php if ((int)$item['cantidad'] > 1): ?>
                                    <span class="inline-flex items-center justify-center px-2 py-0.5 mr-2 rounded-full bg-[#A83232]/10 text-[#A83232] text-xs font-bold">
                                        x<?php echo (int)$item['cantidad']; ?>
                                    </span>
                                <?php endif; ?>
                                Pizza <?php echo $item['nombre_tamano']; ?>
                            </span>
                            <span class="shrink-0 whitespace-nowrap font-bold text-gray-800 pr-1">
                                S/. <?php echo number_format($item['precio_pedido'], 2); ?>
                            </span>
                        </div>

                        <div class="mt-2 border rounded-xl p-2 px-3">
                            <div class="flex justify-between">
                                <span class="font-bold">
                                    <?php echo $item['nombre_producto']; ?>
                                </span>
                                <span class="text-sm text-gray-400">
                                    S/. <?php echo number_format($item['precio_tamano'], 2); ?>
                                </span>
                            </div>
                        </div>

                        <div class="mt-2 border rounded-xl p-2 px-3">
                            <div class="flex justify-between">
                                <span class="font-bold">
                                    <?php echo $item['nombre_segunda_mitad']; ?>
                                </span>
                                <span class="text-sm text-gray-400">
                                    S/. <?php echo number_format($item['precio_segunda_mitad'], 2); ?>
                                </span>
                            </div>
                        </div>

                        <div class="text-xs mt-2 text-gray-500">
                            <?php echo htmlspecialchars($item['detalle'] ?? ''); ?>
                        </div>

                    </div>
                </div>
                <?php else: ?>
                <div class="pedido-item flex gap-3 items-start p-3 md:p-4 rounded-2xl border border-gray-200 hover:bg-gray-50 transition-colors" data-id="<?= $item['id_pedido'] ?>">

                    <div class="w-6 h-6 rounded bg-[#A83232]/10 text-[#A83232] flex items-center justify-center font-bold text-xs mt-1">
                        <?php echo $contador++; ?>
                    </div>

                    <div class="flex-1">

                        <div class="flex justify-between">
                            <span class="flex-1 min-w-0 font-head font-bold text-gray-800 leading-snug break-words">
                                <?php if ((int)$item['cantidad'] > 1): ?>
                                    <span class="inline-flex items-center justify-center px-2 py-0.5 mr-2 rounded-full bg-[#A83232]/10 text-[#A83232] text-xs font-bold">
                                        x<?php echo (int)$item['cantidad']; ?>
                                    </span>
                                <?php endif; ?>
                                <?php
                                    if ($item['categoria'] === 'pizza') {
                                        echo "Pizza ";
                                    }
                                    echo $item['nombre_producto'] . " " . $item['nombre_tamano'];
                                ?>
                            </span>

                            <span class="shrink-0 whitespace-nowrap font-bold text-gray-800 pr-1">
                                S/. <?php echo number_format($item['precio_pedido'], 2); ?>
                            </span>
                        </div>

                        <div class="text-xs text-gray-500 mt-1">
                            <?php echo htmlspecialchars($item['detalle'] ?? ''); ?>
                        </div>

                    </div>
                </div>
                <?php endif; ?>

                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($puedeAcciones): ?>
            <a 
                href="carta.php?origen=<?php echo urlencode($origen); ?>&id=<?php echo urlencode($idRecibido); ?>"
                class="absolute bottom-3 md:bottom-5 right-3 md:right-5 w-12 h-12 text-3xl rounded-full bg-[#A83232] text-white flex items-center justify-center hover:bg-[#8a2525] shadow-xl z-50">
                <i class="fa-solid fa-plus"></i>
            </a>
        <?php endif; ?>
        
    </main>

    <?php if (!$modoSoloMesa): ?>
    <footer class="p-4 pb-12 md:pb-4 bg-[#F9F7F1] border-t border-gray-200 shadow-[0_-5px_15px_rgba(0,0,0,0.02)]">
        <div class="mb-4 text-sm">
            <div class="flex justify-between text-gray-500">
                <span>Subtotal</span>
                <span>S/. <?php echo number_format($subtotal, 2); ?></span>
            </div>
            <?php foreach ($modificaciones as $mod): ?>
            <div class="flex justify-between text-gray-500">
                <span>
                    <?php echo $mod['tipo']; ?>
                    <?php 
                        if ($mod['opcion'] === 'Porcentaje') {
                            echo " ({$mod['monto_original']}%)";
                        } else {
                            echo " (S/. {$mod['monto_original']})";
                        }
                    ?>
                </span>
                <span>
                    S/. <?php echo number_format($mod['monto_calculado'], 2); ?>
                </span>
            </div>
            <?php endforeach; ?>
            <div class="flex justify-between items-center mt-3 pt-3 border-t border-gray-200">
                <span class="font-head font-extrabold text-xl text-[#2C2C2C]">Total</span>
                <span class="font-head font-extrabold text-xl text-[#A83232]">
                    S/. <?php echo number_format($total, 2); ?>
                </span>
            </div>
        </div>

        <?php if ($puedeAcciones): ?>
        <div class="grid grid-cols-4 gap-2 mb-3">
            <button id="btnModificacion" class="col-span-1 py-1.5 md:py-3 rounded-2xl border border-[#A83232] text-[#A83232] hover:bg-[#A83232]/10 font-bold transition-colors flex flex-col md:flex-row items-center justify-center gap-1">
                <i class="fa-regular fa-pen-to-square"></i>
                <span class="text-xs md:text-sm">Modificar</span>
            </button>
            <button id="btnCancelarPedido" class="col-span-1 py-1.5 md:py-3 rounded-2xl border border-[#A83232] text-[#A83232] hover:bg-[#A83232]/10 font-bold transition-colors flex flex-col md:flex-row items-center justify-center gap-1">
                <i class="fa-solid fa-ban"></i>
                <span class="text-xs md:text-sm">Cancelar</span>
            </button>
            <button id="btnNuevoPedido" class="col-span-2 py-1.5 md:py-3 rounded-2xl bg-[#556B2F] text-white hover:bg-[#435525] font-bold shadow-md transition-transform active:scale-95 flex items-center justify-center gap-2">
                <i class="fa-solid fa-plus"></i>
                NUEVA ORDEN
            </button>
        </div>

        <?php if ($tieneMesa): ?>
        <button id="btnConfirmarPedido" class="w-full py-2 md:py-3 rounded-full bg-[#A83232] text-white font-head font-extrabold text-lg shadow-lg hover:shadow-xl hover:-translate-y-1 transition-all flex items-center justify-center gap-3">
            <span>IMPRIMIR PEDIDOS</span>
            <i class="fa-solid fa-check"></i>
        </button>
        <?php else: ?>
        <div class="grid grid-cols-2 gap-2 mb-3">
        <button id="btnImprimirDetalle" class="w-full py-2 md:py-3 rounded-full border border-[#A83232] text-[#A83232] font-head font-extrabold text-lg shadow-lg hover:bg-[#A83232]/10 hover:shadow-xl hover:-translate-y-1 transition-all flex items-center justify-center gap-3">
            <i class="fa-solid fa-print"></i>    
            <span>IMPRIMIR DETALLE</span>
        </button>
        <button id="btnPagarPedido" class="w-full py-2 md:py-3 rounded-full bg-[#A83232] text-white font-head font-extrabold text-lg shadow-lg hover:shadow-xl hover:-translate-y-1 transition-all flex items-center justify-center gap-3">
            <span>CONFIRMAR PAGO</span>
            <i class="fa-solid fa-check"></i>
        </button>
        </div>
        <?php endif; ?>

        <?php elseif ($puedeReabrir): ?>
        <button id="btnReabrirOrden" class="w-full py-2 md:py-3 rounded-full bg-[#A83232] text-white font-head font-extrabold text-lg shadow-lg hover:shadow-xl hover:-translate-y-1 transition-all flex items-center justify-center gap-3">
            <span>REABRIR ORDEN</span>
            <i class="fa-solid fa-check"></i>
        </button>
        <?php endif; ?>
    </footer>
    <?php endif; ?>

    <?php if ($puedeAcciones): ?>
    <div id="pedidoMenu"
        class="hidden fixed bg-white shadow-xl rounded-2xl p-3 pb-0 gap-2 z-50">
        <button id="btnEditarPedido"
            class="mb-3 px-4 py-2 rounded-xl border border-[#A83232] text-[#A83232] bg-white font-bold">
            Editar
        </button>
        <button id="btnEliminarPedido"
            class="mb-3 px-4 py-2 rounded-xl bg-[#A83232] text-white font-bold">
            Eliminar
        </button>
    </div>

    <div id="modalModificacion"
         class="hidden absolute inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
        <div class="flex w-auto h-auto rounded-3xl bg-white flex-col m-8 p-5 pt-5 gap-4 relative">
            <div class="flex justify-between items-center">
                <h3 class="font-head font-bold text-[#2C2C2C] text-xl leading-tight">Agregar Modificación</h3>
                <button id="btnCerrarModalModificacion"
                        class="w-10 h-10 text-2xl rounded-full bg-white shadow-sm flex items-center justify-center text-[#A83232] hover:bg-[#A83232] hover:text-white transition-colors">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="flex gap-3 items-center">
                <span class="font-head font-extrabold text-[#A83232] text-lg">Tipo:</span>
                <select id="selectTipo" class="p-3 bg-white border-none rounded-full shadow-sm font-head text-[#A83232] text-base focus:ring-2 focus:ring-[#A83232] outline-none transition-all">
                    <option value="Aumento">Aumento</option>
                    <option value="Descuento">Descuento</option>
                </select>
            </div>
            <div class="flex gap-3 items-center">
                <span class="font-head font-extrabold text-[#A83232] text-lg">Opción:</span>
                <select id="selectOpcion" class="p-3 bg-white border-none rounded-full shadow-sm font-head text-[#A83232] text-base focus:ring-2 focus:ring-[#A83232] outline-none transition-all">
                    <option value="Porcentaje">Porcentaje</option>
                    <option value="Estatico">Estático</option>
                </select>
            </div>
            <div class="flex gap-3 items-center">
                <span id="labelMontoTexto" class="font-head font-extrabold text-[#A83232] text-lg">
                Monto (%):
                </span>
                <input id="inputMonto" type="text" placeholder="%"
                       class="w-28 px-4 py-3 font-head font-extrabold text-[#A83232] text-base bg-white rounded-full shadow-sm placeholder-gray-400 ring-2 ring-[#A83232] outline-none transition-all">
            </div>
            <button id="btnAgregarModificacion"
                    class="w-auto h-12 text-lg px-4 rounded-full bg-[#A83232] text-white flex items-center justify-center hover:bg-[#8a2525] shadow-md transition-colors">
                <i class="fa-solid fa-plus"></i>
                <span class="font-head ml-3 font-bold text-base">AGREGAR MODIFICACIÓN</span>
            </button>
            <h3 class="font-head font-bold text-[#2C2C2C] text-xl leading-tight mt-2 mb-1">Eliminar Modificación</h3>
            <div id="bloqueModificacion" class="flex flex-col gap-2 items-start">
                <span class="font-head font-extrabold text-[#A83232] text-lg">Modificación:</span>
                <select id="selectModificacion" class="p-3 bg-white border-none rounded-full shadow-sm font-head text-[#A83232] text-base focus:ring-2 focus:ring-[#A83232] outline-none transition-all">
                    <?php foreach ($modificaciones as $mod): ?>
                        <?php
                            if ($mod['opcion'] === 'Porcentaje') {
                                echo "<option value=\"{$mod['id']}\">{$mod['tipo']} ({$mod['monto_original']}%)</option>";
                            } else {
                                echo "<option value=\"{$mod['id']}\">{$mod['tipo']} (S/. {$mod['monto_original']})</option>";
                            }
                        ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <button id="btnEliminarModificacion"
                    class="w-auto h-12 text-xl px-4 rounded-full bg-[#A83232] text-white flex items-center justify-center hover:bg-[#8a2525] shadow-md transition-colors">
                <i class="fa-solid fa-trash fa-xs"></i>
                <span class="font-head ml-3 font-bold text-base">ELIMINAR MODIFICACIÓN</span>
            </button>
            <h3 class="font-head font-bold text-[#2C2C2C] text-xl leading-tight mt-2 mb-1">Mover Orden</h3>
            <div id="bloqueMesa" class="flex gap-3 items-center">
                <span class="font-head font-extrabold text-[#A83232] text-lg">Mover a:</span>
                <select id="selectMesa" class="p-3 bg-white border-none rounded-full shadow-sm font-head text-[#A83232] text-base focus:ring-2 focus:ring-[#A83232] outline-none transition-all">
                    <?php foreach($mesasLibres as $m): ?>
                    <option value="<?= $m['id'] ?>"><?= htmlspecialchars(textoMesa((int)$m['id'])) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button id="btnMoverOrden"
                    class="w-auto h-12 text-xl px-4 rounded-full bg-white border border-[#A83232] text-[#A83232] flex items-center justify-center hover:bg-gray-200 shadow-md transition-colors">
                <i class="fa-solid fa-pen-to-square"></i>
                <span class="font-head ml-3 font-bold text-lg">MOVER ORDEN</span>
            </button>
        </div>
    </div>

    <div id="modalProducto"
         class="hidden absolute inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
        <div class="flex w-full max-w-md rounded-3xl bg-white flex-col m-4 p-5 pt-5 gap-4 relative">
            <div class="flex justify-between items-center">
                <div class="min-w-0">
                    <h3 id="modalNombre" class="font-head font-bold text-[#2C2C2C] text-xl leading-tight mb-1">Americana</h3>
                    <p id="modalDesc" class="text-sm truncate">Queso y jamón.</p>
                </div>
                <button id="btnCerrarModalProducto"
                        class="w-10 h-10 text-2xl rounded-full bg-white shadow-sm flex items-center justify-center text-[#A83232] hover:bg-[#A83232] hover:text-white transition-colors">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="w-auto h-37 rounded-2xl bg-gray-100 overflow-hidden relative">
                <img id="modalImg" src="img/4_Estaciones.webp" class="w-full h-full object-cover">
            </div>
            <div id="bloqueTamano" class="flex gap-2 items-center">
                <span class="font-head font-extrabold text-[#A83232] text-base">Tamaño:</span>
                <select id="selectTamano" class="p-2 bg-white border-none rounded-full shadow-sm font-head text-[#A83232] text-sm focus:ring-2 focus:ring-[#A83232] outline-none transition-all">
                    <option value="Media">Media</option>
                    <option value="Grande">Grande</option>
                    <option value="Familiar">Familiar</option>
                </select>
            </div>
            <div id="bloqueParteSabor" class="flex flex-col gap-2 items-start">
                <div class="flex gap-2 items-center">
                    <span class="font-head font-extrabold text-[#A83232] text-base">Parte:</span>
                    <select id="selectParte" class="p-2 bg-white border-none rounded-full shadow-sm font-head text-[#A83232] text-sm focus:ring-2 focus:ring-[#A83232] outline-none transition-all">
                        <option value="Entera">Entera</option>
                        <option value="Mitad">Mitad</option>
                    </select>
                </div>
                <div class="flex gap-2 items-center">
                    <span id="labelSabor" class="font-head font-extrabold text-[#A83232] text-base">Sabor:</span>
                    <select id="selectSabor" class="p-2 bg-white border-none rounded-full shadow-sm font-head text-[#A83232] text-sm focus:ring-2 focus:ring-[#A83232] outline-none transition-all">
                        <option value="Americana">Americana</option>
                        <option value="Pizza Sebastiani">Pizza Sebastiani</option>
                    </select>
                </div>
            </div>
            <textarea id="detallePedido" autocomplete="off" rows="2"
                      class="block w-full rounded-md px-3.5 py-2 text-base font-head text-[#A83232] outline-2 -outline-offset-1 outline-[#A83232]"
                      placeholder="Detalle del pedido"></textarea>
            <div class="flex items-center gap-3">
                <span class="font-head font-extrabold text-[#A83232] text-base">Impreso:</span>
                <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                    <input id="checkboxImpreso" type="checkbox"
                        class="w-5 h-5 accent-[#A83232]">
                </label>
            </div>
            <div class="flex justify-between items-center">
                <div class="flex gap-3 items-center">
                    <div class="flex gap-2 items-center">
                        <span class="font-head font-extrabold text-[#A83232] text-base">Cant:</span>
                        <input id="cantidadPedido" type="number" min="1" step="1" value="1" class="w-16 px-3 py-2 font-head font-extrabold text-[#A83232] text-base bg-white border-none rounded-full shadow-sm focus:ring-2 focus:ring-[#A83232] outline-none transition-all">
                    </div>
                    <div class="flex gap-2 items-center">
                        <span class="font-head font-extrabold text-[#A83232] text-lg">S/.</span>
                        <input id="precioManual" type="text" class="w-21 px-3 py-2 font-head font-extrabold text-[#A83232] text-base bg-white border-none rounded-full shadow-sm placeholder-gray-400 focus:ring-2 focus:ring-[#A83232] outline-none transition-all">
                    </div>
                </div>
            </div>
            <button id="btnGuardarPedido"
                    class="text-lg px-3 py-2 gap-2 rounded-full bg-[#A83232] text-white flex items-center justify-center hover:bg-[#8a2525] shadow-md transition-colors">
                <i class="fa-solid fa-floppy-disk"></i>
                <span class="font-head font-bold text-base">GUARDAR</span>
            </button>
        </div>
    </div>
    <?php endif; ?>

    <script>
    function on(id, event, callback) {
        const el = document.getElementById(id);
        if (el) el.addEventListener(event, callback);
    }

    const ORDEN_ACTUAL = <?= $ordenActual ? (int)$ordenActual : 'null' ?>;
    const VOLVER_URL = <?= json_encode($salidaPorCerrar, JSON_UNESCAPED_UNICODE) ?>;
    const PUDE_ACCIONES = <?= $puedeAcciones ? 'true' : 'false' ?>;
    const PUDE_REABRIR = <?= $puedeReabrir ? 'true' : 'false' ?>;

    on("btnConfirmarPedido", "click", async () => {
        const idOrden = ORDEN_ACTUAL;
        const mesa = <?= json_encode($mesa_id, JSON_UNESCAPED_UNICODE) ?>;

        window.open(
            "ticket_cocina.php?id_orden=" + idOrden + "&mesa=" + mesa + "&origen=" + <?= json_encode($origen, JSON_UNESCAPED_UNICODE) ?>,
            "_blank",
            "width=400,height=700"
        );
    });

    on("btnImprimirDetalle", "click", async () => {
        if (ORDEN_ACTUAL === null) return;

        const mesa = <?= json_encode($mesa_id, JSON_UNESCAPED_UNICODE) ?>;
        const origen = <?= json_encode($origen, JSON_UNESCAPED_UNICODE) ?>;

        window.open(
            "ticket_detalle.php?id_orden=" + ORDEN_ACTUAL + "&mesa=" + mesa + "&origen=" + origen,
            "_blank",
            "width=400,height=700"
        );
    });

    on("btnPagarPedido", "click", async () => {
        if (ORDEN_ACTUAL === null) return;

        const res = await fetch("orden.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                accion: "pagar",
                id_orden: ORDEN_ACTUAL
            })
        });

        const result = await res.json();
        if (result.success) {
            window.location.href = "caja.php";
        }
    });

    on("btnReabrirOrden", "click", async () => {
        if (ORDEN_ACTUAL === null) return;

        const res = await fetch("orden.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                accion: "reabrir",
                id_orden: ORDEN_ACTUAL,
                origen: "caja"
            })
        });

        const result = await res.json();
        if (result.success) {
            window.location.href = "caja.php";
        }
    });

    on("btnCancelarPedido", "click", async () => {
        if (!ORDEN_ACTUAL) return;

        if (!confirm("¿Cancelar esta orden?")) return;

        const res = await fetch("cancelar_orden.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                id_orden: ORDEN_ACTUAL,
                origen: <?= json_encode($origen, JSON_UNESCAPED_UNICODE) ?>
            })
        });

        const result = await res.json().catch(() => null);

        if (result && result.success) {
            window.location.href = VOLVER_URL;
        }
    });

    on("btnNuevoPedido", "click", async () => {
        if (!ORDEN_ACTUAL) return;

        if (!confirm("¿Cerrar esta orden y crear una nueva orden?")) return;

        const res = await fetch("cerrar_y_nueva_orden.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                id_orden: ORDEN_ACTUAL,
                origen: <?= json_encode($origen, JSON_UNESCAPED_UNICODE) ?>
            })
        });

        const result = await res.json();

        if (result.success) {
            if (result.origen === "caja") {
                window.location.href = "orden.php?origen=caja&id=" + result.nueva_orden;
            } else {
                window.location.href = "orden.php?origen=mesa&id=" + result.mesa;
            }
        }
    });

    <?php if ($puedeAcciones): ?>
    const modalMod = document.getElementById("modalModificacion");
    const selectOpcion = document.getElementById("selectOpcion");
    const labelMonto = document.getElementById("labelMontoTexto");
    const PRODUCTOS = <?= json_encode($productos, JSON_UNESCAPED_UNICODE) ?>;
    const modalProducto = document.getElementById("modalProducto");
    const selectTamano = document.getElementById("selectTamano");
    const selectParte = document.getElementById("selectParte");
    const selectSabor = document.getElementById("selectSabor");
    const checkboxImpreso = document.getElementById("checkboxImpreso");
    const cantidadPedido = document.getElementById("cantidadPedido");

    let holdTimer;
    let pedidoSeleccionado = null;
    let productoActual = null;

    const menu = document.getElementById("pedidoMenu");
    const modalNombre = document.getElementById("modalNombre");
    const modalDesc = document.getElementById("modalDesc");
    const modalImg = document.getElementById("modalImg");
    const bloqueParteSabor = document.getElementById("bloqueParteSabor");
    const btnGuardar = document.getElementById("btnGuardarPedido");

    on("btnModificacion", "click", () => {
        if (modalMod) {
            modalMod.classList.remove("hidden");
            modalMod.classList.add("flex");
        }
    });

    on("btnCerrarModalModificacion", "click", () => {
        if (modalMod) modalMod.classList.add("hidden");
    });

    if (selectOpcion && labelMonto) {
        selectOpcion.addEventListener("change", () => {
            const inputMonto = document.getElementById("inputMonto");

            if (!inputMonto) return;

            if (selectOpcion.value === "Porcentaje") {
                labelMonto.textContent = "Monto (%):";
                inputMonto.placeholder = "%";
            } else {
                labelMonto.textContent = "Monto (S/.):";
                inputMonto.placeholder = "S/.";
            }
        });
    }

    on("btnAgregarModificacion", "click", async () => {
        const tipo = document.getElementById("selectTipo")?.value;
        const opcion = document.getElementById("selectOpcion")?.value;
        const monto = parseFloat(document.getElementById("inputMonto")?.value || "0");

        await fetch("guardar_modificacion.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                id_orden: ORDEN_ACTUAL,
                tipo: tipo,
                opcion: opcion,
                monto: monto
            })
        });

        location.reload();
    });

    on("btnEliminarModificacion", "click", async () => {
        const idMod = document.getElementById("selectModificacion")?.value;
        if (!idMod) return;

        await fetch("eliminar_modificacion.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ id: idMod })
        });

        location.reload();
    });

    on("btnMoverOrden", "click", async () => {
        const mesa = document.getElementById("selectMesa")?.value;
        if (!mesa) return;

        const res = await fetch("mover_orden.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                id_orden: ORDEN_ACTUAL,
                mesa: mesa
            })
        });

        const result = await res.json().catch(() => null);

        if (result && result.success) {
            window.location.href = <?= json_encode($origen === 'caja' ? 'caja.php' : 'mesas.php', JSON_UNESCAPED_UNICODE) ?>;
        }
    });

    document.querySelectorAll(".pedido-item").forEach(item => {
        item.addEventListener("contextmenu", e => {
            e.preventDefault();
            abrirMenu(item, e.pageX, e.pageY);
        });
        item.addEventListener("touchstart", e => {
            holdTimer = setTimeout(() => {
                const touch = e.touches[0];
                abrirMenu(item, touch.pageX, touch.pageY);
            }, 600);
        });
        item.addEventListener("touchend", () => {
            clearTimeout(holdTimer);
        });
    });

    function abrirMenu(item, x, y) {
        if (!menu) return;
        menu.classList.add("hidden");
        pedidoSeleccionado = item.dataset.id;
        menu.style.top = y + "px";
        menu.style.left = x + "px";
        menu.classList.remove("hidden");
    }

    document.addEventListener("click", e => {
        if (menu && !menu.contains(e.target)) {
            menu.classList.add("hidden");
        }
    });

    on("btnEliminarPedido", "click", async () => {
        if (!pedidoSeleccionado) return;

        await fetch("eliminar_pedido.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ id: pedidoSeleccionado })
        });

        location.reload();
    });

    function getPrecioPorTamano(prod, tamanoIndex) {
        if (!prod.precios[tamanoIndex]) return prod.precios[0].precio;
        return prod.precios[tamanoIndex].precio;
    }

    function llenarSabores(tamanoIndex) {
        if (!selectSabor) return;

        selectSabor.innerHTML = "";

        Object.entries(PRODUCTOS)
            .filter(([id, p]) => p.categoria === "pizza")
            .forEach(([id, p]) => {
                const precio = getPrecioPorTamano(p, tamanoIndex);
                const opt = document.createElement("option");
                opt.value = id;
                opt.textContent = `${p.nombre} — S/. ${precio}`;
                selectSabor.appendChild(opt);
            });
    }

    on("btnEditarPedido", "click", async () => {
        if (!pedidoSeleccionado) return;

        if (menu) menu.classList.add("hidden");

        const res = await fetch("obtener_pedido.php?id=" + pedidoSeleccionado);
        const result = await res.json();

        if (!result.success) {
            alert("Error al cargar");
            return;
        }

        const data = result.data;
        const prod = PRODUCTOS[data.id_producto];
        if (!prod) return;

        productoActual = prod;

        modalNombre.textContent = prod.nombre;
        modalDesc.textContent = prod.descripcion ?? "";

        modalImg.src = "img/" + prod.nombre.replace(/ /g, "_") + ".webp";
        modalImg.onerror = () => modalImg.src = "img/default.webp";

        const bloqueTamano = document.getElementById("bloqueTamano");
        selectTamano.innerHTML = "";

        if (prod.precios.length > 1) {
            bloqueTamano.style.display = "flex";

            prod.precios.forEach((p) => {
                const opt = document.createElement("option");
                opt.value = p.id;
                opt.textContent = `${p.tamano} — S/. ${p.precio}`;
                selectTamano.appendChild(opt);
            });

            selectTamano.value = String(data.id_precio);
        } else {
            bloqueTamano.style.display = "none";
            selectTamano.innerHTML = "";
        }

        if (prod.categoria === "pizza") {
            bloqueParteSabor.style.display = "flex";

            if (data.id_segundo_producto) {
                selectParte.value = "Mitad";
                document.getElementById("labelSabor").style.display = "inline";
                selectSabor.style.display = "inline";
            } else {
                selectParte.value = "Entera";
                document.getElementById("labelSabor").style.display = "none";
                selectSabor.style.display = "none";
            }

            llenarSabores(selectTamano.selectedIndex);

            if (data.id_segundo_producto) {
                selectSabor.value = String(data.id_segundo_producto);
            }
        } else {
            bloqueParteSabor.style.display = "none";
            selectParte.value = "Entera";
            selectSabor.innerHTML = "";
        }

        document.getElementById("detallePedido").value = data.detalle ?? "";
        cantidadPedido.value = Math.max(1, parseInt(data.cantidad || "1", 10));
        document.getElementById("precioManual").value = data.precio;
        if (checkboxImpreso) {
            checkboxImpreso.checked = Number(data.impreso) === 1;
        }

        btnGuardar.dataset.editando = data.id;
        btnGuardar.dataset.idprecio = data.id_precio;
        btnGuardar.dataset.usatamanos = prod.precios.length > 1 ? "1" : "0";
        btnGuardar.dataset.precioManual = "1";

        actualizarPrecioManual();

        modalProducto.classList.remove("hidden");
        modalProducto.classList.add("flex");
    });

    btnGuardar.addEventListener("click", async function() {
        const editandoId = this.dataset.editando ?? null;

        const detalle = document.getElementById("detallePedido").value;
        const precio  = parseFloat(document.getElementById("precioManual").value || "0");
        const cantidad = Math.max(1, parseInt(cantidadPedido.value || "1", 10));

        const usaTamanos = this.dataset.usatamanos === "1";
        let idPrecio = parseInt(this.dataset.idprecio, 10);

        if (usaTamanos) {
            idPrecio = parseInt(selectTamano.value, 10);
        }

        const esMitad =
            productoActual &&
            productoActual.categoria === "pizza" &&
            selectParte.value === "Mitad";

        let idSegundoProducto = null;

        if (esMitad) {
            idSegundoProducto = selectSabor.value;
        }

        const impreso = checkboxImpreso && checkboxImpreso.checked ? 1 : 0;

        if (!editandoId) {
            alert("Error: no hay ID para editar");
            return;
        }

        await fetch("actualizar_pedido.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                id_pedido: editandoId,
                id_precio: idPrecio,
                detalle: detalle,
                precio: precio,
                cantidad: cantidad,
                mitad: esMitad,
                id_segundo_producto: idSegundoProducto,
                impreso: impreso
            })
        });

        delete this.dataset.editando;
        delete this.dataset.idprecio;
        delete this.dataset.usatamanos;

        location.reload();
    });

    on("btnCerrarModalProducto", "click", () => {
        if (modalProducto) {
            modalProducto.classList.add("hidden");
            modalProducto.classList.remove("flex");
        }

        document.getElementById("detallePedido").value = "";
        document.getElementById("precioManual").value = "";
        cantidadPedido.value = "1";
        if (checkboxImpreso) checkboxImpreso.checked = false;
        selectParte.value = "Entera";
        selectSabor.innerHTML = "";
        selectTamano.innerHTML = "";
    });

    function actualizarPrecioManual() {
        if (!productoActual) return;

        const input = document.getElementById("precioManual");
        const tamanoIndex = selectTamano.selectedIndex;
        const cantidad = Math.max(1, parseInt(cantidadPedido.value || "1", 10));

        const precioTamano =
            parseFloat(productoActual.precios[tamanoIndex]?.precio
            ?? productoActual.precios[0].precio);

        if (productoActual.categoria !== "pizza") {
            if (btnGuardar.dataset.precioManual === "1") return;
            input.value = (precioTamano * cantidad).toFixed(2);
            return;
        }

        if (selectParte.value === "Entera") {
            if (btnGuardar.dataset.precioManual === "1") return;
            input.value = (precioTamano * cantidad).toFixed(2);
            return;
        }

        const saborId = selectSabor.value;
        const saborProd = PRODUCTOS[saborId];

        if (!saborProd) {
            if (btnGuardar.dataset.precioManual === "1") return;
            input.value = (precioTamano * cantidad).toFixed(2);
            return;
        }

        const precioSabor =
            parseFloat(saborProd.precios[tamanoIndex]?.precio
            ?? saborProd.precios[0].precio);

        input.value = (Math.max(precioTamano, precioSabor) * cantidad).toFixed(2);
    }

    if (selectTamano) {
        selectTamano.addEventListener("change", () => {
            if (!productoActual) return;

            btnGuardar.dataset.precioManual = "0";

            if (productoActual.categoria === "pizza") {
                llenarSabores(selectTamano.selectedIndex);
            }

            actualizarPrecioManual();
        });
    }

    if (selectSabor) {
        selectSabor.addEventListener("change", () => {
            btnGuardar.dataset.precioManual = "0";
            actualizarPrecioManual();
        });
    }

    if (selectParte) {
        selectParte.addEventListener("change", () => {
            btnGuardar.dataset.precioManual = "0";
            
            if (selectParte.value === "Mitad") {
                document.getElementById("labelSabor").style.display = "inline";
                selectSabor.style.display = "inline";
            } else {
                document.getElementById("labelSabor").style.display = "none";
                selectSabor.style.display = "none";
            }

            actualizarPrecioManual();
        });
    }

    if (cantidadPedido) {
        cantidadPedido.addEventListener("input", () => {
            btnGuardar.dataset.precioManual = "0";
            actualizarPrecioManual();
        });
    }
    <?php endif; ?>
    </script>
</body>
</html>
