<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header('Content-Type: text/html; charset=UTF-8');

require_once("conexion.php");

$filtro = $_GET['estado'] ?? 'abierta';
$estadosValidos = ['abierta', 'pagada', 'cancelada', 'todas'];

if (!in_array($filtro, $estadosValidos)) {
    $filtro = 'abierta';
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
        WHERE o.estado = ?
        ORDER BY o.fecha_creacion DESC
    ";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("s", $filtro);
}

$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {
    echo '
        <div class="col-span-full text-center py-16 text-gray-400">
            <i class="fa-solid fa-receipt text-5xl mb-4"></i>
            <p class="text-lg font-semibold">No hay órdenes para mostrar</p>
        </div>
    ';
    exit;
}

while ($row = $resultado->fetch_assoc()) {
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
            <i class="fa-solid fa-users mr-1"></i>Mesa <?php echo htmlspecialchars($row['mesa']); ?>
        </div>
        <div class="text-base font-bold" style="color: <?php echo $color; ?>">
            S/. <?php echo number_format((float)$row['total'], 2); ?>
        </div>
        <div class="text-sm text-gray-400 mt-1">
            <?php echo date("h:i A - d/m/Y", strtotime($row['fecha_creacion'])); ?>
        </div>
    </div>
    <?php
}