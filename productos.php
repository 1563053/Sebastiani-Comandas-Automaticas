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

function responderJson(array $data): void
{
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function baseImagenProducto(string $nombre): string
{
    return str_replace(' ', '_', trim($nombre));
}

function rutaImagenProducto(string $nombre): string
{
    return "img/" . baseImagenProducto($nombre) . ".webp";
}

function validarYGuardarWebp(array $file, string $destinoAbsoluto): array
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return [false, null, null];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No se pudo subir la imagen.');
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'webp') {
        throw new RuntimeException('La imagen debe ser .webp');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if ($mime !== 'image/webp') {
        throw new RuntimeException('La imagen debe ser tipo webp.');
    }

    $destDir = dirname($destinoAbsoluto);
    if (!is_dir($destDir)) {
        throw new RuntimeException('La carpeta de imágenes no existe.');
    }

    if (file_exists($destinoAbsoluto)) {
        unlink($destinoAbsoluto);
    }

    if (!move_uploaded_file($file['tmp_name'], $destinoAbsoluto)) {
        throw new RuntimeException('No se pudo guardar la imagen.');
    }

    return [true, $destinoAbsoluto, basename($destinoAbsoluto)];
}

function normalizarCategoria(string $categoria): string
{
    $categoria = strtolower(trim($categoria));
    return in_array($categoria, ['pizza', 'extra', 'bebida', 'pasta'], true) ? $categoria : 'extra';
}

function obtenerTamanosPost(): array
{
    $ids = $_POST['tamano_id'] ?? [];
    $nombres = $_POST['tamano_nombre'] ?? [];
    $precios = $_POST['tamano_precio'] ?? [];

    if (!is_array($ids)) {
        $ids = [];
    }
    if (!is_array($nombres)) {
        $nombres = [];
    }
    if (!is_array($precios)) {
        $precios = [];
    }

    $tamanos = [];
    $cantidad = max(count($ids), count($nombres), count($precios));

    for ($i = 0; $i < $cantidad; $i++) {
        $id = trim((string)($ids[$i] ?? ''));
        $nombre = trim((string)($nombres[$i] ?? ''));
        $precioRaw = trim((string)($precios[$i] ?? '0'));

        if ($precioRaw === '') {
            $precioRaw = '0';
        }

        if ($nombre === '' && $precioRaw === '0' && $id === '') {
            continue;
        }

        if (!is_numeric($precioRaw)) {
            throw new RuntimeException('Todos los precios deben ser numéricos.');
        }

        $tamanos[] = [
            'id' => ($id !== '' ? (int)$id : null),
            'nombre' => $nombre,
            'precio' => (float)$precioRaw,
        ];
    }

    return $tamanos;
}

function cargarProductoCompleto(mysqli $conexion, int $productoId): ?array
{
    $stmt = $conexion->prepare("\n        SELECT id, nombre, descripcion, categoria, estado\n        FROM producto\n        WHERE id = ?\n        LIMIT 1\n    ");
    $stmt->bind_param('i', $productoId);
    $stmt->execute();
    $producto = $stmt->get_result()->fetch_assoc();

    if (!$producto) {
        return null;
    }

    $stmtPrecios = $conexion->prepare("\n        SELECT id, nombre, precio\n        FROM detalle_precio\n        WHERE id_producto = ?\n        ORDER BY id ASC\n    ");
    $stmtPrecios->bind_param('i', $productoId);
    $stmtPrecios->execute();
    $resPrecios = $stmtPrecios->get_result();

    $precios = [];
    while ($row = $resPrecios->fetch_assoc()) {
        $precios[] = $row;
    }

    $producto['precios'] = $precios;
    $producto['imagen'] = rutaImagenProducto($producto['nombre']);
    return $producto;
}

$mensajeError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];

    try {
        if ($accion === 'eliminar_producto') {
            $productoId = (int)($_POST['producto_id'] ?? 0);
            if ($productoId <= 0) {
                responderJson(['success' => false, 'error' => 'ID inválido']);
            }

            $stmt = $conexion->prepare("\n                SELECT p.id, p.nombre\n                FROM producto p\n                WHERE p.id = ?\n                LIMIT 1\n            ");
            $stmt->bind_param('i', $productoId);
            $stmt->execute();
            $producto = $stmt->get_result()->fetch_assoc();

            if (!$producto) {
                responderJson(['success' => false, 'error' => 'Producto no encontrado']);
            }

            $stmtRef = $conexion->prepare("\n                SELECT COUNT(*) AS total\n                FROM detalle_pedido dp\n                INNER JOIN detalle_precio pr ON pr.id = dp.id_precio\n                WHERE pr.id_producto = ?\n            ");
            $stmtRef->bind_param('i', $productoId);
            $stmtRef->execute();
            $ref = $stmtRef->get_result()->fetch_assoc();

            if ((int)($ref['total'] ?? 0) > 0) {
                responderJson(['success' => false, 'error' => 'No se puede eliminar porque ya fue usado en pedidos.']);
            }

            $conexion->begin_transaction();

            $rutaImagen = __DIR__ . '/' . rutaImagenProducto($producto['nombre']);
            if (is_file($rutaImagen)) {
                unlink($rutaImagen);
            }

            $stmtDel = $conexion->prepare("DELETE FROM producto WHERE id = ?");
            $stmtDel->bind_param('i', $productoId);
            if (!$stmtDel->execute()) {
                throw new RuntimeException('No se pudo eliminar el producto.');
            }

            $conexion->commit();
            responderJson(['success' => true]);
        }

        if ($accion === 'guardar_producto') {
            $productoId = (int)($_POST['producto_id'] ?? 0);
            $nombre = trim((string)($_POST['nombre'] ?? ''));
            $descripcion = trim((string)($_POST['descripcion'] ?? ''));
            $categoria = normalizarCategoria((string)($_POST['categoria'] ?? 'extra'));
            $estado = (isset($_POST['disponible']) && (string)$_POST['disponible'] === '1') ? 'disponible' : 'nodis';
            $tamanos = obtenerTamanosPost();
            $imagenNueva = $_FILES['imagen'] ?? null;

            if ($nombre === '') {
                responderJson(['success' => false, 'error' => 'El nombre es obligatorio.']);
            }

            if ($productoId <= 0 && (!$imagenNueva || ($imagenNueva['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE)) {
                responderJson(['success' => false, 'error' => 'Debes seleccionar una imagen webp.']);
            }

            if ($productoId <= 0) {
                if (count($tamanos) === 0) {
                    if ($categoria === 'pizza') {
                        $tamanos = [
                            ['id' => null, 'nombre' => 'Media', 'precio' => 0],
                            ['id' => null, 'nombre' => 'Grande', 'precio' => 0],
                            ['id' => null, 'nombre' => 'Familiar', 'precio' => 0],
                        ];
                    } else {
                        $tamanos = [
                            ['id' => null, 'nombre' => '', 'precio' => 0],
                        ];
                    }
                }
            } else {
                $productoAnterior = cargarProductoCompleto($conexion, $productoId);
                if (!$productoAnterior) {
                    responderJson(['success' => false, 'error' => 'Producto no encontrado.']);
                }
            }

            $rutaImagenRelNueva = rutaImagenProducto($nombre);
            $rutaImagenAbsNueva = __DIR__ . '/' . $rutaImagenRelNueva;

            $conexion->begin_transaction();
            $imagenMovida = false;
            $imagenTemporal = false;
            $rutaImagenAbsAnterior = null;
            $nombreAnterior = null;
            $rutaImagenAbsViejaRenombrada = null;

            try {
                if ($productoId <= 0) {
                    $stmtIns = $conexion->prepare("
                        INSERT INTO producto (nombre, descripcion, categoria, estado)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmtIns->bind_param('ssss', $nombre, $descripcion, $categoria, $estado);
                    if (!$stmtIns->execute()) {
                        throw new RuntimeException('No se pudo crear el producto.');
                    }
                    $productoId = (int)$conexion->insert_id;

                    if ($imagenNueva && (($imagenNueva['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE)) {
                        validarYGuardarWebp($imagenNueva, $rutaImagenAbsNueva);
                        $imagenMovida = true;
                    }
                } else {
                    $stmtSel = $conexion->prepare("\n                        SELECT nombre\n                        FROM producto\n                        WHERE id = ?\n                        LIMIT 1\n                    ");
                    $stmtSel->bind_param('i', $productoId);
                    $stmtSel->execute();
                    $fila = $stmtSel->get_result()->fetch_assoc();
                    if (!$fila) {
                        throw new RuntimeException('Producto no encontrado.');
                    }

                    $nombreAnterior = $fila['nombre'];
                    $rutaImagenAbsAnterior = __DIR__ . '/' . rutaImagenProducto($nombreAnterior);

                    if ($imagenNueva && (($imagenNueva['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE)) {
                        validarYGuardarWebp($imagenNueva, $rutaImagenAbsNueva);
                        $imagenMovida = true;
                    } elseif ($nombreAnterior !== $nombre && is_file($rutaImagenAbsAnterior)) {
                        if (file_exists($rutaImagenAbsNueva)) {
                            unlink($rutaImagenAbsNueva);
                        }
                        if (!rename($rutaImagenAbsAnterior, $rutaImagenAbsNueva)) {
                            throw new RuntimeException('No se pudo renombrar la imagen anterior.');
                        }
                        $rutaImagenAbsViejaRenombrada = $rutaImagenAbsAnterior;
                        $imagenTemporal = true;
                    }

                    $stmtUpd = $conexion->prepare("\n                        UPDATE producto\n                        SET nombre = ?, descripcion = ?, categoria = ?, estado = ?\n                        WHERE id = ?\n                    ");
                    $stmtUpd->bind_param('ssssi', $nombre, $descripcion, $categoria, $estado, $productoId);
                    if (!$stmtUpd->execute()) {
                        throw new RuntimeException('No se pudo actualizar el producto.');
                    }
                }

                $stmtPreciosAct = $conexion->prepare("\n                    SELECT id, nombre, precio\n                    FROM detalle_precio\n                    WHERE id_producto = ?\n                    ORDER BY id ASC\n                ");
                $stmtPreciosAct->bind_param('i', $productoId);
                $stmtPreciosAct->execute();
                $resPreciosAct = $stmtPreciosAct->get_result();

                $preciosActuales = [];
                while ($row = $resPreciosAct->fetch_assoc()) {
                    $preciosActuales[(int)$row['id']] = $row;
                }

                $idsMantenidos = [];
                $contadorTamanos = count($tamanos);

                if ($contadorTamanos === 0) {
                    if ($categoria === 'pizza') {
                        $tamanos = [
                            ['id' => null, 'nombre' => 'Media', 'precio' => 0],
                            ['id' => null, 'nombre' => 'Grande', 'precio' => 0],
                            ['id' => null, 'nombre' => 'Familiar', 'precio' => 0],
                        ];
                    } else {
                        $tamanos = [
                            ['id' => null, 'nombre' => '', 'precio' => 0],
                        ];
                    }
                }

                foreach ($tamanos as $idx => $tamano) {
                    $idTam = $tamano['id'];
                    $nombreTam = trim((string)$tamano['nombre']);
                    $precioTam = (float)$tamano['precio'];

                    if ($categoria === 'pizza' && $nombreTam === '') {
                        $defaults = ['Media', 'Grande', 'Familiar'];
                        $nombreTam = $defaults[$idx] ?? ('Tamaño ' . ($idx + 1));
                    }

                    if ($idTam !== null && isset($preciosActuales[$idTam])) {
                        $stmtUpdPrecio = $conexion->prepare("\n                            UPDATE detalle_precio\n                            SET nombre = ?, precio = ?\n                            WHERE id = ? AND id_producto = ?\n                        ");
                        $stmtUpdPrecio->bind_param('sdii', $nombreTam, $precioTam, $idTam, $productoId);
                        if (!$stmtUpdPrecio->execute()) {
                            throw new RuntimeException('No se pudo actualizar un tamaño.');
                        }
                        $idsMantenidos[] = $idTam;
                    } else {
                        $stmtInsPrecio = $conexion->prepare("\n                            INSERT INTO detalle_precio (id_producto, nombre, precio)\n                            VALUES (?, ?, ?)\n                        ");
                        $stmtInsPrecio->bind_param('isd', $productoId, $nombreTam, $precioTam);
                        if (!$stmtInsPrecio->execute()) {
                            throw new RuntimeException('No se pudo crear un tamaño.');
                        }
                        $idsMantenidos[] = (int)$conexion->insert_id;
                    }
                }

                foreach ($preciosActuales as $idPrecio => $rowPrecio) {
                    if (!in_array($idPrecio, $idsMantenidos, true)) {
                        $stmtRefPrecio = $conexion->prepare("\n                            SELECT COUNT(*) AS total\n                            FROM detalle_pedido\n                            WHERE id_precio = ?\n                        ");
                        $stmtRefPrecio->bind_param('i', $idPrecio);
                        $stmtRefPrecio->execute();
                        $refPrecio = $stmtRefPrecio->get_result()->fetch_assoc();

                        if ((int)($refPrecio['total'] ?? 0) > 0) {
                            throw new RuntimeException('No se puede eliminar un tamaño que ya fue usado en pedidos.');
                        }

                        $stmtDelPrecio = $conexion->prepare("DELETE FROM detalle_precio WHERE id = ? AND id_producto = ?");
                        $stmtDelPrecio->bind_param('ii', $idPrecio, $productoId);
                        if (!$stmtDelPrecio->execute()) {
                            throw new RuntimeException('No se pudo eliminar un tamaño.');
                        }
                    }
                }

                $conexion->commit();

                responderJson([
                    'success' => true,
                    'producto_id' => $productoId,
                    'imagen' => rutaImagenProducto($nombre),
                ]);
            } catch (Throwable $e) {
                $conexion->rollback();

                if (isset($rutaImagenAbsNueva) && is_file($rutaImagenAbsNueva) && $imagenMovida) {
                    unlink($rutaImagenAbsNueva);
                }

                if ($imagenMovida && is_file($rutaImagenAbsNueva)) {
                    unlink($rutaImagenAbsNueva);
                }

                if ($imagenTemporal && $rutaImagenAbsViejaRenombrada && is_file($rutaImagenAbsNueva)) {
                    @rename($rutaImagenAbsNueva, $rutaImagenAbsViejaRenombrada);
                }

                responderJson(['success' => false, 'error' => $e->getMessage()]);
            }
        }

        responderJson(['success' => false, 'error' => 'Acción no válida']);
    } catch (Throwable $e) {
        responderJson(['success' => false, 'error' => $e->getMessage()]);
    }
}

$sql = "
SELECT 
    p.id,
    p.nombre,
    p.descripcion,
    p.categoria,
    p.estado,
    dp.id AS id_precio,
    dp.nombre AS tamano,
    dp.precio
FROM producto p
JOIN detalle_precio dp ON dp.id_producto = p.id
ORDER BY p.id, dp.precio
";

$result = $conexion->query($sql);

$productos = [];
while ($row = $result->fetch_assoc()) {
    $id = $row['id'];

    if (!isset($productos[$id])) {
        $productos[$id] = [
            'id' => (int)$row['id'],
            'nombre' => $row['nombre'],
            'descripcion' => $row['descripcion'],
            'categoria' => $row['categoria'],
            'estado' => $row['estado'],
            'imagen' => rutaImagenProducto($row['nombre']),
            'precios' => []
        ];
    }

    $productos[$id]['precios'][] = [
        'id' => (int)$row['id_precio'],
        'tamano' => $row['tamano'],
        'precio' => (float)$row['precio']
    ];
}

$totalProductos = count($productos);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos - Sebastiani POS</title>
    <link rel="stylesheet" href="src/output.css">
    <link rel="stylesheet" href="src/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700;800&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="h-dvh w-screen overflow-hidden flex text-sm lg:text-base bg-white">
    <div class="h-full w-full flex">
        <aside class="w-24 bg-white border-r border-gray-200 flex flex-col items-center py-6 z-20 shadow-sm shrink-0">
            <button type="button" onclick="window.location.href='caja.php'" class="w-14 h-14 rounded-2xl bg-[#A83232] flex items-center justify-center text-white text-2xl font-bold mb-10 shadow-lg cursor-pointer hover:scale-105 transition-transform">
                <img class="flex items-center justify-center rounded-2xl" src="img/SebastianiLogo1.png" alt="Logo">
            </button>

            <nav class="flex-1 flex flex-col gap-6 w-full px-4">
                <button type="button" onclick="window.location.href='caja.php'" class="w-full aspect-square rounded-2xl bg-[#A83232] text-white flex flex-col items-center justify-center gap-1 shadow-md transition-all">
                    <i class="fa-solid fa-cash-register text-xl"></i>
                    <span class="text-[10px] font-head font-bold">Órdenes</span>
                </button>
                <button type="button" onclick="window.location.href='productos.php'" class="w-full aspect-square rounded-2xl bg-[#2C2C2C] text-white flex flex-col items-center justify-center gap-1 shadow-md transition-all">
                    <i class="fa-solid fa-boxes-stacked text-xl"></i>
                    <span class="text-[10px] font-head font-bold">Productos</span>
                </button>
            </nav>
        </aside>

        <main class="flex-1 flex flex-col h-full overflow-hidden relative">
            <header class="px-6 py-4 flex flex-col gap-4 items-center bg-[#F9F7F1] shrink-0 border-b border-gray-100">
                <div class="w-full max-w-6xl flex items-center justify-between">
                    <div class="flex flex-col">
                        <h1 class="text-xl font-head font-extrabold text-[#2C2C2C]">Productos</h1>
                        <p class="text-gray-500 text-sm font-semibold">
                            <?= $totalProductos ?> producto<?= $totalProductos === 1 ? '' : 's' ?> registrados
                        </p>
                    </div>
                </div>

                <div class="relative w-full max-w-6xl flex items-center justify-between">
                    <input id="buscador" type="text" placeholder="Buscar producto..."
                        class="w-full pl-12 pr-4 py-3 bg-white border-none rounded-full shadow-sm text-gray-700 placeholder-gray-400 focus:ring-2 focus:ring-[#A83232] outline-none transition-all">
                    <i class="fa-solid fa-magnifying-glass absolute left-5 top-1/2 -translate-y-1/2 text-gray-400"></i>
                </div>
            </header>

            <div class="flex-1 overflow-y-auto px-6 pb-8 custom-scrollbar">
                <section class="pt-5">
                    <div class="flex gap-3 mb-5 overflow-x-auto pb-2 scrollbar-hide">
                        <button class="btn-pill cat-btn px-4 py-2.5 font-head font-bold transition-all shrink-0 bg-[#2C2C2C] text-white shadow-lg"
                            data-cat="todo">
                            <i class="fa-solid fa-layer-group mr-2"></i>Todo
                        </button>
                        <button class="btn-pill cat-btn px-4 py-2.5 font-head font-bold transition-all shrink-0 bg-white text-[#2C2C2C] shadow-sm border border-gray-100 cursor-pointer"
                            data-cat="pizza">
                            <i class="fa-solid fa-pizza-slice mr-2"></i>Pizzas
                        </button>
                        <button class="btn-pill cat-btn px-4 py-2.5 font-head font-bold transition-all shrink-0 bg-white text-[#2C2C2C] shadow-sm border border-gray-100 cursor-pointer"
                            data-cat="pasta">
                            <i class="fa-solid fa-utensils mr-2"></i>Pastas
                        </button>
                        <button class="btn-pill cat-btn px-4 py-2.5 font-head font-bold transition-all shrink-0 bg-white text-[#2C2C2C] shadow-sm border border-gray-100 cursor-pointer"
                            data-cat="extra">
                            <i class="fa-solid fa-bowl-food mr-2"></i>Extras
                        </button>
                        <button class="btn-pill cat-btn px-4 py-2.5 font-head font-bold transition-all shrink-0 bg-white text-[#2C2C2C] shadow-sm border border-gray-100 cursor-pointer"
                            data-cat="bebida">
                            <i class="fa-solid fa-wine-glass mr-2"></i>Bebidas
                        </button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-4 gap-4">
                        <?php foreach ($productos as $id => $prod): ?>
                            <?php
                            $noDisp = ($prod['estado'] === 'nodis');
                            $precioTexto = [];
                            foreach ($prod['precios'] as $p) {
                                $precioTexto[] = 'S/. ' . number_format((float)$p['precio'], 2);
                            }
                            $precioStr = implode(' / ', $precioTexto);
                            $imgPath = $prod['imagen'];
                            ?>
                            <div class="producto-item card-modern <?= $noDisp ? 'bg-gray-50 opacity-60 grayscale' : 'bg-white hover:shadow-soft hover:-translate-y-1 cursor-pointer' ?> p-4 relative group transition-all duration-300 rounded-3xl border border-gray-100"
                                data-id="<?= (int)$id ?>"
                                data-cat="<?= htmlspecialchars($prod['categoria']) ?>"
                                data-name="<?= strtolower(htmlspecialchars($prod['nombre'])) ?>">

                                <?php if ($noDisp): ?>
                                    <div class="absolute inset-0 flex items-center justify-center z-10">
                                        <span class="bg-gray-800 text-white text-xs font-bold px-3 py-1 rounded-full">
                                            NO DISPONIBLE
                                        </span>
                                    </div>
                                <?php endif; ?>

                                <div class="w-full h-40 rounded-2xl bg-gray-100 mb-3 overflow-hidden">
                                    <img src="<?= htmlspecialchars($imgPath) ?>"
                                        onerror="this.src='img/default.webp'"
                                        class="w-full h-full object-cover">
                                </div>

                                <div class="flex items-start justify-between gap-3 mb-1">
                                    <h3 class="font-head font-bold text-[#2C2C2C] text-lg leading-tight">
                                        <?= htmlspecialchars($prod['nombre']) ?>
                                    </h3>
                                    <span class="text-[11px] font-bold px-2 py-1 rounded-full <?= $noDisp ? 'bg-gray-200 text-gray-500' : 'bg-[#A83232]/10 text-[#A83232]' ?>">
                                        <?= htmlspecialchars($prod['categoria']) ?>
                                    </span>
                                </div>

                                <p class="text-xs text-gray-400 mb-3 truncate">
                                    <?= htmlspecialchars($prod['descripcion'] ?? '') ?>
                                </p>

                                <div class="space-y-1">
                                    <div class="flex justify-between items-center">
                                        <span class="font-head font-extrabold text-lg <?= $noDisp ? 'text-gray-400' : 'text-[#A83232]' ?>">
                                            <?= htmlspecialchars($precioStr) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>

            <button id="btnNuevoProducto"
                class="absolute bottom-5 right-5 w-14 h-14 rounded-full bg-[#A83232] text-white flex items-center justify-center hover:bg-[#8a2525] shadow-xl z-40">
                <i class="fa-solid fa-plus text-2xl"></i>
            </button>

            <div id="menuProducto"
                class="hidden fixed bg-white shadow-xl rounded-2xl p-3 pb-0 gap-2 z-50">
                <button id="btnEditarProducto"
                    class="mb-3 px-4 py-2 rounded-xl border border-[#A83232] text-[#A83232] bg-white font-bold w-full">
                    Editar
                </button>
                <button id="btnEliminarProducto"
                    class="mb-3 px-4 py-2 rounded-xl bg-[#A83232] text-white font-bold w-full">
                    Eliminar
                </button>
            </div>

            <div id="modalProducto"
                class="hidden absolute inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 overflow-y-auto">
                <div class="flex w-full max-w-2xl rounded-3xl bg-white flex-col m-6 p-5 pt-5 gap-4 relative max-h-[92vh] overflow-y-auto">
                    <div class="flex justify-between items-center gap-3">
                        <div class="min-w-0">
                            <h3 id="modalTitulo" class="font-head font-bold text-[#2C2C2C] text-xl leading-tight mb-1">Nuevo producto</h3>
                            <p id="modalSubtitulo" class="text-sm text-gray-500">Completa los datos del producto</p>
                        </div>
                        <button id="btnCerrarModalProducto"
                            class="w-10 h-10 text-2xl rounded-full bg-white shadow-sm flex items-center justify-center text-[#A83232] hover:bg-[#A83232] hover:text-white transition-colors shrink-0">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-4">
                            <div>
                                <label class="block font-head font-bold text-[#A83232] mb-2">Nombre</label>
                                <input id="inputNombre" type="text" class="w-full px-4 py-3 rounded-2xl border border-gray-200 focus:ring-2 focus:ring-[#A83232] outline-none" placeholder="Nombre del producto">
                            </div>

                            <div>
                                <label class="block font-head font-bold text-[#A83232] mb-2">Imagen .webp</label>
                                <input id="inputImagen" type="file" accept="image/webp,.webp" class="w-full px-4 py-3 rounded-2xl border border-gray-200 bg-white focus:ring-2 focus:ring-[#A83232] outline-none">
                                <p class="text-xs text-gray-400 mt-2">Se guardará usando el nombre del producto con espacios reemplazados por “_”.</p>
                                <div class="mt-3 rounded-2xl bg-gray-100 overflow-hidden border border-gray-200">
                                    <img id="previewImagen" src="img/default.webp" class="w-full h-48 object-cover">
                                </div>
                            </div>

                            <div>
                                <label class="block font-head font-bold text-[#A83232] mb-2">Descripción</label>
                                <textarea id="inputDescripcion" rows="3" class="w-full px-4 py-3 rounded-2xl border border-gray-200 focus:ring-2 focus:ring-[#A83232] outline-none" placeholder="Descripción"></textarea>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block font-head font-bold text-[#A83232] mb-2">Categoría</label>
                                    <select id="selectCategoria" class="w-full px-4 py-3 rounded-2xl border border-gray-200 focus:ring-2 focus:ring-[#A83232] outline-none bg-white">
                                        <option value="extra">Extra</option>
                                        <option value="pizza">Pizza</option>
                                        <option value="pasta">Pastas</option>
                                        <option value="bebida">Bebida</option>
                                    </select>
                                </div>
                                <div class="flex items-end">
                                    <label class="inline-flex items-center gap-3 cursor-pointer select-none w-full h-12 px-4 rounded-2xl border border-gray-200">
                                        <input id="checkboxDisponible" type="checkbox" class="w-5 h-5 accent-[#A83232]" checked>
                                        <span class="font-semibold text-gray-700">Disponible</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="flex items-center justify-between gap-3">
                                <label class="block font-head font-bold text-[#A83232]">Tamaños</label>
                                <button id="btnAgregarTamano" type="button" class="px-4 py-2 rounded-full border border-[#A83232] text-[#A83232] font-bold hover:bg-[#A83232]/10 transition-colors">
                                    Agregar tamaño
                                </button>
                            </div>

                            <div id="listaTamanos" class="space-y-3"></div>
                        </div>
                    </div>

                    <div class="pt-2 flex justify-end">
                        <button id="btnGuardarProducto"
                            class="text-lg px-5 py-3 gap-2 rounded-full bg-[#A83232] text-white flex items-center justify-center hover:bg-[#8a2525] shadow-md transition-colors">
                            <i class="fa-solid fa-floppy-disk"></i>
                            <span class="font-head font-bold text-base">GUARDAR</span>
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        const PRODUCTOS = <?= json_encode($productos, JSON_UNESCAPED_UNICODE) ?>;
        const botonesCategoria = document.querySelectorAll('.cat-btn');
        const cards = document.querySelectorAll('.producto-item');
        const buscador = document.getElementById('buscador');
        const menu = document.getElementById('menuProducto');
        const modal = document.getElementById('modalProducto');
        const btnNuevoProducto = document.getElementById('btnNuevoProducto');
        const btnCerrarModalProducto = document.getElementById('btnCerrarModalProducto');
        const btnEditarProducto = document.getElementById('btnEditarProducto');
        const btnEliminarProducto = document.getElementById('btnEliminarProducto');
        const btnGuardarProducto = document.getElementById('btnGuardarProducto');
        const btnAgregarTamano = document.getElementById('btnAgregarTamano');
        const selectCategoria = document.getElementById('selectCategoria');
        const checkboxDisponible = document.getElementById('checkboxDisponible');
        const inputNombre = document.getElementById('inputNombre');
        const inputImagen = document.getElementById('inputImagen');
        const inputDescripcion = document.getElementById('inputDescripcion');
        const listaTamanos = document.getElementById('listaTamanos');
        const modalTitulo = document.getElementById('modalTitulo');
        const modalSubtitulo = document.getElementById('modalSubtitulo');

        let productoSeleccionado = null;
        let modoEdicion = false;
        let categoriaActiva = 'todo';
        let holdTimer = null;

        function on(id, event, callback) {
            const el = document.getElementById(id);
            if (el) el.addEventListener(event, callback);
        }

        function getImagenProducto(nombre) {
            return 'img/' + nombre.replaceAll(' ', '_') + '.webp';
        }

        function aplicarFiltros() {
            const texto = buscador.value.toLowerCase().trim();

            cards.forEach(card => {
                const cat = card.dataset.cat;
                const nombre = card.dataset.name;
                const matchTexto = nombre.includes(texto);
                const matchCat = categoriaActiva === 'todo' || cat === categoriaActiva;
                card.style.display = (matchTexto && matchCat) ? 'block' : 'none';
            });
        }

        botonesCategoria.forEach(btn => {
            btn.addEventListener('click', () => {
                categoriaActiva = btn.dataset.cat;

                botonesCategoria.forEach(b => {
                    b.classList.remove('bg-[#2C2C2C]', 'text-white', 'shadow-lg');
                    b.classList.add('bg-white', 'text-[#2C2C2C]', 'shadow-sm', 'border', 'border-gray-100', 'cursor-pointer');
                });

                btn.classList.remove('bg-white', 'text-[#2C2C2C]', 'shadow-sm', 'border', 'border-gray-100', 'cursor-pointer');
                btn.classList.add('bg-[#2C2C2C]', 'text-white', 'shadow-lg');
                aplicarFiltros();
            });
        });

        buscador.addEventListener('input', aplicarFiltros);

        function crearFilaTamano(data = {}) {
            const wrapper = document.createElement('div');
            wrapper.className = 'tamano-row grid grid-cols-[1fr_110px_auto] gap-2 items-center rounded-2xl border border-gray-200 p-3';
            wrapper.dataset.tamanoId = data.id ?? '';

            const nombreInput = document.createElement('input');
            nombreInput.type = 'text';
            nombreInput.className = 'tamano-nombre w-full px-3 py-2 rounded-xl border border-gray-200 focus:ring-2 focus:ring-[#A83232] outline-none';
            nombreInput.placeholder = 'Nombre del tamaño';
            nombreInput.value = data.nombre ?? '';

            const precioInput = document.createElement('input');
            precioInput.type = 'number';
            precioInput.step = '0.01';
            precioInput.min = '0';
            precioInput.className = 'tamano-precio w-full px-3 py-2 rounded-xl border border-gray-200 focus:ring-2 focus:ring-[#A83232] outline-none';
            precioInput.placeholder = 'Precio';
            precioInput.value = data.precio ?? '';

            const eliminarBtn = document.createElement('button');
            eliminarBtn.type = 'button';
            eliminarBtn.className = 'tamano-eliminar w-10 h-10 rounded-full bg-[#A83232] text-white flex items-center justify-center hover:bg-[#8a2525]';
            eliminarBtn.innerHTML = '<i class="fa-solid fa-xmark"></i>';
            eliminarBtn.addEventListener('click', () => {
                if (listaTamanos.querySelectorAll('.tamano-row').length === 1) return;
                wrapper.remove();
                actualizarEstadoTamanos();
            });

            wrapper.appendChild(nombreInput);
            wrapper.appendChild(precioInput);
            wrapper.appendChild(eliminarBtn);
            listaTamanos.appendChild(wrapper);
            actualizarEstadoTamanos();
            return wrapper;
        }

        function obtenerFilasTamanos() {
            return Array.from(listaTamanos.querySelectorAll('.tamano-row'));
        }

        function actualizarEstadoTamanos() {
            const filas = obtenerFilasTamanos();
            const multiple = filas.length > 1;

            filas.forEach((fila, index) => {
                const nombreInput = fila.querySelector('.tamano-nombre');
                const eliminarBtn = fila.querySelector('.tamano-eliminar');

                if (multiple) {
                    nombreInput.readOnly = false;
                    nombreInput.classList.remove('bg-gray-100');
                    eliminarBtn.style.display = 'flex';
                } else {
                    nombreInput.readOnly = true;
                    nombreInput.classList.add('bg-gray-100');
                    eliminarBtn.style.display = 'none';
                }

                if (index === 0) {
                    eliminarBtn.style.display = 'none';
                }
            });
        }

        function cargarTamanosDesdeProducto(prod) {
            listaTamanos.innerHTML = '';

            if (prod.precios && prod.precios.length > 0) {
                prod.precios.forEach(p => {
                    crearFilaTamano({
                        id: p.id,
                        nombre: p.tamano ?? '',
                        precio: p.precio ?? ''
                    });
                });
            } else {
                if (prod.categoria === 'pizza') {
                    crearFilaTamano({
                        nombre: 'Media',
                        precio: ''
                    });
                    crearFilaTamano({
                        nombre: 'Grande',
                        precio: ''
                    });
                    crearFilaTamano({
                        nombre: 'Familiar',
                        precio: ''
                    });
                } else {
                    crearFilaTamano({
                        nombre: '',
                        precio: ''
                    });
                }
            }

            actualizarEstadoTamanos();
        }

        function aplicarPlantillaNueva(categoria = 'extra') {
            listaTamanos.innerHTML = '';

            if (categoria === 'pizza') {
                crearFilaTamano({
                    nombre: 'Media',
                    precio: ''
                });
                crearFilaTamano({
                    nombre: 'Grande',
                    precio: ''
                });
                crearFilaTamano({
                    nombre: 'Familiar',
                    precio: ''
                });
            } else {
                crearFilaTamano({
                    nombre: '',
                    precio: ''
                });
            }

            actualizarEstadoTamanos();
        }

        function abrirModalNuevo() {
            modoEdicion = false;
            productoSeleccionado = null;
            modalTitulo.textContent = 'Nuevo producto';
            modalSubtitulo.textContent = 'Completa los datos del producto';
            inputNombre.value = '';
            inputImagen.value = '';
            inputDescripcion.value = '';
            selectCategoria.value = 'extra';
            checkboxDisponible.checked = true;
            const preview = document.getElementById('previewImagen');
            if (preview) {
                preview.src = 'img/default.webp';
                preview.onerror = () => preview.src = 'img/default.webp';
            }
            aplicarPlantillaNueva('extra');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function abrirModalEdicion(id) {
            const prod = PRODUCTOS[id];
            if (!prod) return;

            modoEdicion = true;
            productoSeleccionado = id;
            modalTitulo.textContent = 'Editar producto';
            modalSubtitulo.textContent = 'Modifica los datos y tamaños del producto';
            inputNombre.value = prod.nombre ?? '';
            inputImagen.value = '';
            inputDescripcion.value = prod.descripcion ?? '';
            selectCategoria.value = prod.categoria ?? 'extra';
            checkboxDisponible.checked = (prod.estado === 'disponible');
            cargarTamanosDesdeProducto(prod);

            const preview = document.getElementById('previewImagen');
            if (preview) {
                preview.src = getImagenProducto(prod.nombre);
                preview.onerror = () => preview.src = 'img/default.webp';
            }

            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function cerrarModal() {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            productoSeleccionado = null;
            modoEdicion = false;

            const preview = document.getElementById('previewImagen');
            if (preview) {
                preview.src = 'img/default.webp';
                preview.onerror = () => preview.src = 'img/default.webp';
            }

            inputImagen.value = '';
        }

        function abrirMenu(item, x, y) {
            if (!menu) return;
            menu.classList.add('hidden');
            productoSeleccionado = item.dataset.id;
            menu.style.top = y + 'px';
            menu.style.left = x + 'px';
            menu.classList.remove('hidden');
        }

        cards.forEach(item => {
            item.addEventListener('contextmenu', e => {
                e.preventDefault();
                abrirMenu(item, e.pageX, e.pageY);
            });

            item.addEventListener('touchstart', e => {
                holdTimer = setTimeout(() => {
                    const touch = e.touches[0];
                    abrirMenu(item, touch.pageX, touch.pageY);
                }, 600);
            });

            item.addEventListener('touchend', () => {
                clearTimeout(holdTimer);
            });
        });

        document.addEventListener('click', e => {
            if (menu && !menu.contains(e.target)) {
                menu.classList.add('hidden');
            }
        });

        on('btnNuevoProducto', 'click', abrirModalNuevo);
        on('btnCerrarModalProducto', 'click', cerrarModal);

        on('btnEditarProducto', 'click', () => {
            if (!productoSeleccionado) return;
            if (menu) menu.classList.add('hidden');
            abrirModalEdicion(productoSeleccionado);
        });

        on('btnEliminarProducto', 'click', async () => {
            if (!productoSeleccionado) return;
            if (!confirm('¿Eliminar este producto?')) return;

            const formData = new FormData();
            formData.append('accion', 'eliminar_producto');
            formData.append('producto_id', productoSeleccionado);

            const res = await fetch('productos.php', {
                method: 'POST',
                body: formData
            });

            const result = await res.json().catch(() => null);
            if (result && result.success) {
                location.reload();
            } else {
                alert(result?.error || 'No se pudo eliminar');
            }
        });

        on('btnAgregarTamano', 'click', () => {
            crearFilaTamano({
                nombre: '',
                precio: ''
            });
            const filas = obtenerFilasTamanos();
            if (filas.length > 1) {
                filas.forEach((fila) => {
                    const nombreInput = fila.querySelector('.tamano-nombre');
                    nombreInput.readOnly = false;
                    nombreInput.classList.remove('bg-gray-100');
                });
            }
        });

        on('selectCategoria', 'change', () => {
            if (!modoEdicion) {
                if (selectCategoria.value === 'pizza') {
                    if (obtenerFilasTamanos().length <= 1) {
                        aplicarPlantillaNueva('pizza');
                    }
                } else {
                    if (obtenerFilasTamanos().length === 0) {
                        aplicarPlantillaNueva('extra');
                    }
                }
            }
        });

        on('inputImagen', 'change', () => {
            const file = inputImagen.files && inputImagen.files[0] ? inputImagen.files[0] : null;
            if (!file) return;
            const preview = document.getElementById('previewImagen');
            if (preview) {
                preview.src = URL.createObjectURL(file);
            }
        });

        on('btnGuardarProducto', 'click', async () => {
            const nombre = inputNombre.value.trim();
            const descripcion = inputDescripcion.value.trim();
            const categoria = selectCategoria.value;
            const disponible = checkboxDisponible.checked ? '1' : '0';
            const filas = obtenerFilasTamanos();

            if (!nombre) {
                alert('Ingresa el nombre del producto');
                return;
            }

            if (filas.length === 0) {
                alert('Debes agregar al menos un tamaño');
                return;
            }

            const formData = new FormData();
            formData.append('accion', 'guardar_producto');
            formData.append('producto_id', modoEdicion && productoSeleccionado ? productoSeleccionado : '0');
            formData.append('nombre', nombre);
            formData.append('descripcion', descripcion);
            formData.append('categoria', categoria);
            formData.append('disponible', disponible);

            const guardarNombreTamano = filas.length > 1;

            filas.forEach(fila => {
                formData.append('tamano_id[]', fila.dataset.tamanoId || '');
                formData.append('tamano_nombre[]', guardarNombreTamano ? fila.querySelector('.tamano-nombre').value.trim() : '');
                formData.append('tamano_precio[]', fila.querySelector('.tamano-precio').value.trim());
            });

            if (inputImagen.files && inputImagen.files[0]) {
                formData.append('imagen', inputImagen.files[0]);
            }

            const res = await fetch('productos.php', {
                method: 'POST',
                body: formData
            });

            const result = await res.json().catch(() => null);
            if (result && result.success) {
                location.reload();
            } else {
                alert(result?.error || 'No se pudo guardar');
            }
        });

        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    cerrarModal();
                }
            });
        }

        aplicarFiltros();
        aplicarPlantillaNueva('extra');
    </script>
</body>

</html>
