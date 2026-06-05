<?php
require_once("conexion.php");

$id_orden = isset($_GET['id_orden']) ? (int)$_GET['id_orden'] : 0;
$mesa = isset($_GET['mesa']) ? (int)$_GET['mesa'] : 0;

if ($id_orden <= 0) {
    die("Orden inválida");
}

$stmtOrden = $conexion->prepare("
    SELECT subtotal, total
    FROM orden
    WHERE id = ?
    LIMIT 1
");
$stmtOrden->bind_param("i", $id_orden);
$stmtOrden->execute();
$resOrden = $stmtOrden->get_result()->fetch_assoc();

if (!$resOrden) {
    die("Orden no encontrada");
}

$stmtDetalle = $conexion->prepare("
    SELECT 
        dped.id,
        dped.detalle,
        dped.precio,
        dped.impreso,

        dpre.nombre AS tamano,
        prod.nombre AS producto,
        prod.categoria,

        prod2.nombre AS segunda_mitad

    FROM detalle_pedido dped
    INNER JOIN detalle_precio dpre
        ON dped.id_precio = dpre.id
    INNER JOIN producto prod
        ON dpre.id_producto = prod.id
    LEFT JOIN segunda_mitad sm
        ON sm.id_pedido = dped.id
    LEFT JOIN producto prod2
        ON sm.id_producto = prod2.id
    WHERE dped.id_orden = ?
    ORDER BY dped.id ASC
");
$stmtDetalle->bind_param("i", $id_orden);
$stmtDetalle->execute();

$resultDetalle = $stmtDetalle->get_result();
$pedidos = [];

while ($row = $resultDetalle->fetch_assoc()) {
    $pedidos[] = $row;
}

$stmtMods = $conexion->prepare("
    SELECT tipo, opcion, monto
    FROM modificacion
    WHERE id_orden = ?
    ORDER BY id ASC
");
$stmtMods->bind_param("i", $id_orden);
$stmtMods->execute();
$resMods = $stmtMods->get_result();

$modificaciones = [];
while ($mod = $resMods->fetch_assoc()) {
    $modificaciones[] = $mod;
}

$subtotal = (float)($resOrden['subtotal'] ?? 0);
$total = (float)($resOrden['total'] ?? 0);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle Orden</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: monospace;
            margin: 0;
            font-size: 18px;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 20px;
        }

        .ticket {
            width: 100%;
            max-width: 420px;
        }

        .tituloPantalla {
            font-size: 28px;
            text-align: center;
            font-weight: bold;
            margin-bottom: 18px;
        }

        .header {
            text-align: center;
            font-size: 22px;
            font-weight: bold;
        }

        .line {
            border-top: 2px dashed black;
            margin: 8px 0;
        }

        .box {
            border: 2px solid black;
            padding: 6px;
            margin: 6px 0;
        }

        .filaPedido {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
        }

        .nombrePedido {
            flex: 1;
            min-width: 0;
            font-size: 18px;
            font-weight: bold;
            word-break: break-word;
            overflow-wrap: anywhere;
        }

        .precioPedido {
            flex-shrink: 0;
            white-space: nowrap;
            font-size: 18px;
            font-weight: bold;
            text-align: right;
        }

        .subtexto {
            font-size: 16px;
            margin-top: 4px;
            word-break: break-word;
            overflow-wrap: anywhere;
        }

        .saborLinea {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            font-size: 16px;
            margin-top: 2px;
        }

        .saborTexto {
            flex: 1;
            min-width: 0;
            word-break: break-word;
            overflow-wrap: anywhere;
        }

        .filaTotal {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin: 4px 0;
            font-size: 16px;
        }

        .filaTotal span:last-child {
            white-space: nowrap;
        }

        .btnCerrar {
            width: 100%;
            padding: 20px;
            font-size: 22px;
            font-weight: bold;
            margin-top: 20px;
            background: #A83232;
            color: white;
            border: none;
        }

        @media print {
            body {
                padding: 0;
                display: block;
            }

            .ticket {
                width: 80mm;
                max-width: 80mm;
            }

            .tituloPantalla,
            button {
                display: none;
            }

            @page {
                size: 80mm auto;
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <div class="ticket">
        <div class="tituloPantalla">Detalle de Orden</div>

        <div class="header">
            MESA <?= $mesa ?> - ORDEN <?= $id_orden ?>
        </div>

        <div class="line"></div>

        <?php foreach ($pedidos as $p): ?>
            <?php if ($p['categoria'] === "pizza" && !empty($p['segunda_mitad'])): ?>
                <div class="box">
                    <div class="filaPedido">
                        <div class="nombrePedido">
                            PIZZA <?= htmlspecialchars($p['tamano']) ?>
                        </div>
                        <div class="precioPedido">
                            S/. <?= number_format((float)$p['precio'], 2) ?>
                        </div>
                    </div>

                    <div class="saborLinea">
                        <div class="saborTexto">1/2 <?= htmlspecialchars($p['producto']) ?></div>
                    </div>
                    <div class="saborLinea">
                        <div class="saborTexto">1/2 <?= htmlspecialchars($p['segunda_mitad']) ?></div>
                    </div>

                    <?php if (!empty($p['detalle'])): ?>
                        <div class="subtexto"><?= htmlspecialchars($p['detalle']) ?></div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="filaPedido">
                    <div class="nombrePedido">
                        <?php
                        if ($p['categoria'] === "pizza") {
                            echo "PIZZA " . htmlspecialchars($p['producto']) . " " . htmlspecialchars($p['tamano']);
                        } else {
                            echo htmlspecialchars($p['producto']) . " " . htmlspecialchars($p['tamano']);
                        }
                        ?>
                    </div>
                    <div class="precioPedido">
                        S/. <?= number_format((float)$p['precio'], 2) ?>
                    </div>
                </div>

                <?php if (!empty($p['detalle'])): ?>
                    <div class="subtexto"><?= htmlspecialchars($p['detalle']) ?></div>
                <?php endif; ?>

                <div class="line"></div>
            <?php endif; ?>
        <?php endforeach; ?>

        <div class="line"></div>

        <div class="filaTotal">
            <span>Subtotal</span>
            <span>S/. <?= number_format($subtotal, 2) ?></span>
        </div>

        <?php foreach ($modificaciones as $mod): ?>
            <?php
                $montoCalculado = 0;
                if ($mod['opcion'] === 'porcentaje') {
                    $montoCalculado = ($subtotal * (float)$mod['monto']) / 100;
                } else {
                    $montoCalculado = (float)$mod['monto'];
                }

                if ($mod['tipo'] === 'descuento') {
                    $montoCalculado *= -1;
                }

                $nombreTipo = ucfirst($mod['tipo']);
                $textoOpcion = ($mod['opcion'] === 'porcentaje')
                    ? '(' . $mod['monto'] . '%)'
                    : '(S/. ' . number_format((float)$mod['monto'], 2) . ')';
            ?>
            <div class="filaTotal">
                <span><?= htmlspecialchars($nombreTipo . ' ' . $textoOpcion) ?></span>
                <span>S/. <?= number_format($montoCalculado, 2) ?></span>
            </div>
        <?php endforeach; ?>

        <div class="line"></div>

        <div class="filaTotal" style="font-size:22px;font-weight:bold;">
            <span>TOTAL</span>
            <span>S/. <?= number_format($total, 2) ?></span>
        </div>

        <button class="btnCerrar" onclick="window.close()">CERRAR</button>

        <script>
            window.onload = function () {
                setTimeout(function () {
                    window.print();
                }, 400);
            };
        </script>
    </div>
</body>
</html>