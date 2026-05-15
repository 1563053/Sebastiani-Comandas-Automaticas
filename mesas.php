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
$sql = "
SELECT 
    m.id,
    m.estado,
    o.id AS orden_id,
    o.total AS total,
    o.estado AS estado_orden
FROM mesa m
LEFT JOIN orden o 
    ON o.id = (
        SELECT id 
        FROM orden 
        WHERE id_mesa = m.id 
        ORDER BY id DESC 
        LIMIT 1
    )
ORDER BY m.id
";
$stmt = $conexion->prepare($sql);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result(
    $id,
    $estado,
    $orden_id,
    $total,
    $estado_orden
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mesas - Sebatiani POS</title>
    <link rel="stylesheet" href="src/output.css">
    <link rel="stylesheet" href="src/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700;800&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="h-dvh w-screen overflow-hidden flex text-sm lg:text-base">
    <main class="flex-1 flex flex-col h-full overflow-hidden relative">
        <header class="px-8 py-6 flex justify-between items-center bg-[#F9F7F1] shrink-0">
            <h1 class="text-2xl font-head font-extrabold text-[#2C2C2C]">Seleccionar Mesa</h1>
            <div class="flex gap-3">
                <a href="logout.php"
                   class="w-10 h-10 rounded-full bg-white shadow-sm flex items-center justify-center text-[#A83232] hover:bg-[#A83232] hover:text-white transition-colors">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                </a>
            </div>
        </header>
        <div class="flex-1 overflow-y-auto px-8 pb-8 custom-scrollbar">
            <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-5 gap-4">
                <?php while ($stmt->fetch()): ?>
                    <?php if ($estado == 'ocupada' && $estado_orden != 'abierta') {
                            $update = $conexion->prepare("UPDATE mesa SET estado='libre' WHERE id=?");
                            $update->bind_param("i", $id);
                            $update->execute();
                            $estado = 'libre';
                        }
                    ?>
                    <?php if ($estado == 'libre'): ?>
                        <a href="orden.php?origen=mesa&id=<?php echo intval($id); ?>" class="block">
                            <div class="bg-white p-4 rounded-[20px] shadow-sm border-2 border-dashed border-gray-200 hover:border-[#556B2F] cursor-pointer transition-all group">
                                <div class="flex justify-between items-start mb-2">
                                    <span class="font-head font-bold uppercase text-gray-400 group-hover:text-[#556B2F]">Mesa <?php echo intval($id); ?></span>
                                    <i class="fa-regular fa-circle text-gray-300 group-hover:text-[#556B2F]"></i>
                                </div>
                                <div class="flex flex-col items-center justify-center py-2 text-gray-400">
                                    <i class="fa-solid fa-chair text-2xl mb-1 group-hover:scale-110 transition-transform"></i>
                                    <span class="text-xs font-bold">Libre</span>
                                </div>
                            </div>
                        </a>
                    <?php endif; ?>
                    <?php if ($estado == 'ocupada'): ?>
                        <a href="orden.php?origen=mesa&id=<?php echo intval($id); ?>" class="block">
                            <div class="bg-white p-4 rounded-[20px] shadow-md border-2 hover:border-[#A83232] cursor-pointer relative overflow-hidden transition-all group">
                                <div class="absolute -right-4 -top-4 w-12 h-12 bg-[#A83232]/10 rounded-full"></div>
                                <div class="flex justify-between items-start mb-2 relative z-10">
                                    <span class="font-head font-bold uppercase text-[#2C2C2C] text-lg">Mesa <?php echo intval($id); ?></span>
                                    <span class="bg-[#A83232] text-white text-[10px] font-bold px-2 py-0.5 rounded-full">Ocupada</span>
                                </div>
                                <div class="text-sm font-bold text-[#A83232]">
                                    S/. <?php echo number_format($total, 2); ?>
                                </div>
                                <button class="w-full mt-2 bg-[#A83232] text-white text-xs font-bold py-1.5 rounded-full group-hover:bg-[#8a2525]">
                                    Abrir Pedido
                                </button>
                            </div>
                        </a>
                    <?php endif; ?>
                    <?php if ($estado == 'nodis'): ?>
                        <div class="flex flex-col bg-gray-100 p-4 rounded-[20px] shadow-inner opacity-80 cursor-not-allowed">
                            <div class="flex justify-between items-start mb-2">
                                <span class="font-head font-bold uppercase text-gray-500">Mesa <?php echo intval($numero); ?></span>
                                <i class="fa-solid fa-clock text-gray-400"></i>
                            </div>
                            <div class="flex flex-col items-center justify-center py-5 text-gray-400">
                                <span class="text-sm font-bold uppercase tracking-wider">No disponible</span>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endwhile; ?>
            </div>
        </div>
    </main>
</body>
</html>