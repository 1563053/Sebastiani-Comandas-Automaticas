<?php
require_once("conexion.php");

$id_orden = $_GET['id_orden'] ?? '';
$mesa = $_GET['mesa'] ?? '';
$textoMesa = ((int)$mesa === 0) ? "Delivery" : "MESA " . $mesa;

$stmt = $conexion->prepare("
SELECT 
    dped.id,
    dped.detalle,
    dped.precio,
    dped.cantidad,
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
AND dped.impreso = 0

ORDER BY dped.id
");

$stmt->bind_param("s", $id_orden);
$stmt->execute();

$result = $stmt->get_result();

$pedidos = [];

while($row = $result->fetch_assoc()){
    $pedidos[] = $row;
}
?>

<!DOCTYPE html>

<html>
<head>

<style>
*{
box-sizing:border-box;
}

body{
    font-family: monospace;
    margin:0;
    font-size:20px;

    display:flex;
    justify-content:center;
    align-items:flex-start;
    padding:20px;
}

.ticket{
    width:100%;
    max-width:420px;
}

.tituloPantalla{
    font-size:32px;
    text-align:center;
    font-weight:bold;
    margin-bottom:20px;
}

.header{
    text-align:center;
    font-size:26px;
    font-weight:bold;
}

.producto{
    font-size:24px;
    font-weight:bold;
}

.detalle{
    font-size:18px;
}

.line{
    border-top:2px dashed black;
    margin:8px 0;
}

.center{
    text-align:center;
}

.box{
    border:2px solid black;
    padding:6px;
    margin:6px 0;
}

.btnCerrar{
    width:100%;
    padding:26px;
    font-size:28px;
    font-weight:bold;
    margin-top:20px;
    background:#A83232;
    color:white;
    border:none;
}

@media print{
    body{
        padding:0;
        display:block;
    }

    .ticket{
        width:80mm;
        max-width:80mm;
    }

    .tituloPantalla{
        display:none;
    }

    button{
        display:none;
    }

    @page{
        size:80mm auto;
        margin:0;
    }
}
</style>
</head>

<body>
    <div class="ticket">
        <div class="tituloPantalla">
        Pedidos Mandados a Cocina
        </div>

        <div class="header">
            <?= htmlspecialchars($textoMesa) ?>
            <br>
            ORDEN <?= $id_orden ?>
        </div>

        <div class="line"></div>

        <?php foreach($pedidos as $p): ?>
            <?php if($p['categoria']=="pizza" && $p['segunda_mitad']): ?>
                <div class="producto">
                    <b><?= ((int)$p['cantidad'] > 1) ? 'x' . (int)$p['cantidad'] . ' ' : '' ?>Pizza <?= $p['tamano'] ?></b>
                    <br>
                    <div class="detalle">
                        1/2 <?= $p['producto'] ?> <br>
                        1/2 <?= $p['segunda_mitad'] ?>
                    </div>
                    <?php if($p['detalle']): ?>
                        <br>
                        <?= $p['detalle'] ?>
                    <?php endif; ?>
                </div>
                <div class="line"></div>
            <?php else: ?>
                <div class="producto">
                <?php if($p['categoria']=="pizza"){
                    echo (((int)$p['cantidad'] > 1) ? "x" . (int)$p['cantidad'] . " " : "") . "PIZZA {$p['producto']} {$p['tamano']}";
                }else{
                    echo (((int)$p['cantidad'] > 1) ? "x" . (int)$p['cantidad'] . " " : "") . "{$p['producto']} {$p['tamano']}";
                }
                ?>
                </div>
                <?php if($p['detalle']): ?>
                    <div class="detalle">
                        <?= $p['detalle'] ?>
                    </div>
                <?php endif; ?>
                <div class="line"></div>
            <?php endif; ?>
        <?php endforeach; ?>

        <button class="btnCerrar" onclick="cerrarTicket()">CERRAR</button>
        <script>
        window.onload = function(){
            setTimeout(function(){
                window.print();
            },500);
        }
        async function cerrarTicket(){
            await fetch("confirmar_pedido.php",{
                method:"POST",
                headers:{ "Content-Type":"application/json"},
                body: JSON.stringify({
                    id_orden:"<?= $id_orden ?>"
                })
            });
            if(window.opener){
                window.opener.location.href = "mesas.php";
            }
            window.close();
        }
        </script>
    </div>
</body>
</html>
