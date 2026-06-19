<?php
session_start();
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once("conexion.php");

$filtro = $_GET['estado'] ?? 'abierta';
$estadosValidos = ['abierta', 'pagada', 'cancelada', 'todas'];

if (!in_array($filtro, $estadosValidos)) {
    $filtro = 'abierta';
}

$where = "";
if ($filtro != "todas") {
    $where = "WHERE o.estado = ?";
}

if ($filtro == "todas") {
    $sql = "
        SELECT 
            o.id,
            o.id_mesa AS mesa,
            o.estado,
            o.total,
            o.fecha_creacion
        FROM orden o
        ORDER BY o.fecha_creacion DESC
    ";
    $stmt = $conexion->prepare($sql);
} else {
    $sql = "
        SELECT 
            o.id,
            o.id_mesa AS mesa,
            o.estado,
            o.total,
            o.fecha_creacion
        FROM orden o
        $where
        ORDER BY o.fecha_creacion DESC
    ";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("s", $filtro);
}

$stmt->execute();
$resultado = $stmt->get_result();

function estiloPill($estadoActual, $valor){
    if($estadoActual == $valor){
        return "bg-[#2C2C2C] text-white";
    }else{
        return "bg-white text-[#2C2C2C] border border-gray-100 hover:bg-[#A83232] hover:text-white";
    }
}

function colorEstado($estado) {
    switch ($estado) {
        case 'abierta':
            return ['#A83232', 'bg-white border-[#A83232]', 'Pendiente'];
        case 'pagada':
            return ['#D4A017', 'bg-[#FFFBF0] border-[#D4A017]', 'Pagada'];
        case 'cancelada':
            return ['gray', 'bg-gray-100 border-gray-500', 'Cancelada'];
        default:
            return ['#2C2C2C', 'bg-white border-gray-200', 'Sin estado'];
    }
}

function textoMesa(int $id): string {
    return $id === 0 ? "Delivery" : "Mesa " . $id;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Caja - Sebatiani POS</title>
    <link rel="stylesheet" href="src/output.css">
    <link rel="stylesheet" href="src/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700;800&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="h-screen w-screen overflow-hidden flex text-sm lg:text-base">
    <aside class="w-24 bg-white border-r border-gray-200 flex flex-col items-center py-6 z-20 shadow-sm shrink-0">
        <button class="w-14 h-14 bg-[#A83232] rounded-2xl flex items-center justify-center text-white text-2xl font-bold mb-10 shadow-lg cursor-pointer hover:scale-105 transition-transform">
            <img class="flex items-center justify-center rounded-2xl" src="img/SebastianiLogo1.png" alt="Logo">
        </button>

        <nav class="flex-1 flex flex-col gap-6 w-full px-4">
            <button type="button" onclick="window.location.href='caja.php'" class="w-full aspect-square rounded-2xl bg-[#2C2C2C] text-white flex flex-col items-center justify-center gap-1 shadow-md transition-all">
                <i class="fa-solid fa-cash-register text-xl"></i>
                <span class="text-[10px] font-head font-bold">Órdenes</span>
            </button>
            <button type="button" onclick="window.location.href='productos.php'" class="w-full aspect-square rounded-2xl bg-[#A83232] text-white flex flex-col items-center justify-center gap-1 shadow-md transition-all">
                <i class="fa-solid fa-boxes-stacked text-xl"></i>
                <span class="text-[10px] font-head font-bold">Productos</span>
            </button>
        </nav>
    </aside>

    <main class="flex-1 flex flex-col h-full overflow-hidden relative">
        <header class="px-8 py-6 flex justify-between items-center bg-[#F9F7F1] shrink-0">
            <div class="flex flex-col">
                <h1 class="text-2xl font-head font-extrabold text-[#2C2C2C]">Caja</h1>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto px-8 pb-8 custom-scrollbar">
            <section class="mb-10">
                <div class="flex gap-3 mb-6 overflow-x-auto pb-2">
                    <button onclick="filtrar('abierta')"
                        class="btn-pill px-6 py-2.5 font-head font-bold shadow-sm transition-all shrink-0 <?php echo estiloPill($filtro,'abierta'); ?>">
                        <i class="fa-solid fa-clock mr-2"></i>Pendientes
                    </button>

                    <button onclick="filtrar('pagada')" 
                        class="btn-pill px-6 py-2.5 font-head font-bold shadow-sm transition-all shrink-0 <?php echo estiloPill($filtro,'pagada'); ?>">
                        <i class="fa-solid fa-pizza-slice mr-2"></i>Pagadas
                    </button>

                    <button onclick="filtrar('cancelada')" 
                        class="btn-pill px-6 py-2.5 font-head font-bold shadow-sm transition-all shrink-0 <?php echo estiloPill($filtro,'cancelada'); ?>">
                        <i class="fa-solid fa-ban mr-2"></i>Canceladas
                    </button>

                    <button onclick="filtrar('todas')"
                        class="btn-pill px-6 py-2.5 font-head font-bold shadow-sm transition-all shrink-0 <?php echo estiloPill($filtro,'todas'); ?>">
                        <i class="fa-solid fa-border-all mr-2"></i>Todas
                    </button>
                </div>

                <div id="contenedorOrdenes" class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-5 gap-4">
                    <?php if ($resultado->num_rows > 0): ?>
                        <?php while($row = $resultado->fetch_assoc()):
                            [$color, $bg, $estadoTexto] = colorEstado($row['estado']);
                        ?>
                            <div onclick="abrirOrden(<?php echo (int)$row['id']; ?>)"
                                 class="p-4 rounded-[20px] shadow-md border-l-4 cursor-pointer relative overflow-hidden <?php echo $bg; ?> hover:scale-[1.02] hover:shadow-lg transition-all">
                                <div class="absolute -right-4 -top-4 w-12 h-12 bg-[#A83232]/10 rounded-full"></div>
                                <div class="flex justify-between items-start mb-1">
                                    <span class="font-bold text-lg">Orden <?php echo (int)$row['id']; ?></span>
                                    <span class="text-white text-[10px] font-bold px-2 py-0.5 rounded-full" style="background: <?php echo $color; ?>">
                                        <?php echo $estadoTexto; ?>
                                    </span>
                                </div>
                                <div class="text-sm text-gray-500 mb-1">
                                    <i class="fa-solid fa-users mr-1"></i><?php echo htmlspecialchars(textoMesa((int)$row['mesa'])); ?>
                                </div>
                                <div class="text-base font-bold" style="color: <?php echo $color; ?>">
                                    S/. <?php echo number_format((float)$row['total'], 2); ?>
                                </div>
                                <div class="text-sm text-gray-400 mt-1">
                                    <?php echo date("h:i A - d/m/Y", strtotime($row['fecha_creacion'])); ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-span-full text-center py-16 text-gray-400">
                            <i class="fa-solid fa-receipt text-5xl mb-4"></i>
                            <p class="text-lg font-semibold">No hay órdenes para mostrar</p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </main>

    <script>
        let filtroActual = <?php echo json_encode($filtro); ?>;
        let intervaloRecarga = null;

        function setPillsActivos(estado) {
            const botones = document.querySelectorAll('.btn-pill');
            botones.forEach(btn => {
                btn.classList.remove('bg-[#2C2C2C]', 'text-white');
                btn.classList.add('bg-white', 'text-[#2C2C2C]', 'border', 'border-gray-100');
            });

            const btnActivo = Array.from(botones).find(btn => {
                return btn.getAttribute('onclick') && btn.getAttribute('onclick').includes(`'${estado}'`);
            });

            if (btnActivo) {
                btnActivo.classList.remove('bg-white', 'text-[#2C2C2C]', 'border', 'border-gray-100');
                btnActivo.classList.add('bg-[#2C2C2C]', 'text-white');
            }
        }

        async function cargarOrdenes() {
            const contenedor = document.getElementById('contenedorOrdenes');
            try {
                const respuesta = await fetch(`cargar_ordenes.php?estado=${encodeURIComponent(filtroActual)}`, {
                    cache: 'no-store'
                });

                const html = await respuesta.text();
                contenedor.innerHTML = html;
                setPillsActivos(filtroActual);
            } catch (error) {
                console.error('Error al cargar órdenes:', error);
            }
        }

        function filtrar(estado) {
            filtroActual = estado;
            const nuevaUrl = new URL(window.location.href);
            nuevaUrl.searchParams.set('estado', estado);
            history.replaceState({}, '', nuevaUrl);
            cargarOrdenes();
        }

        function abrirOrden(id) {
            window.location.href = `orden.php?id=${id}&origen=caja`;
        }

        document.addEventListener('DOMContentLoaded', () => {
            setPillsActivos(filtroActual);
            cargarOrdenes();

            intervaloRecarga = setInterval(() => {
                cargarOrdenes();
            }, 5000);
        });
    </script>
</body>
</html>
