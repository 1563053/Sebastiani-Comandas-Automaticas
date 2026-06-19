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

function textoMesa(int $id): string {
    return $id === 0 ? "Delivery" : "Mesa " . str_pad((string)$id, 2, "0", STR_PAD_LEFT);
}

$origen = strtolower($_GET['origen'] ?? ($_POST['origen'] ?? ''));
$idPresente = isset($_GET['id']) || isset($_POST['id']);
$idRecibido = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);

if ($origen !== 'mesa' && $origen !== 'caja') {
    header("Location: mesas.php");
    exit;
}

if (!$idPresente || ($origen === 'caja' && $idRecibido <= 0) || ($origen === 'mesa' && $idRecibido < 0)) {
    header("Location: " . ($origen === 'caja' ? "caja.php" : "mesas.php"));
    exit;
}

$mesa_id = 0;
$orden_id = 0;
$estadoOrden = null;

if ($origen === 'caja') {
    $orden_id = $idRecibido;

    $stmtOrden = $conexion->prepare("
        SELECT id_mesa, estado
        FROM orden
        WHERE id = ?
        LIMIT 1
    ");
    $stmtOrden->bind_param("i", $orden_id);
    $stmtOrden->execute();
    $resOrden = $stmtOrden->get_result()->fetch_assoc();

    if (!$resOrden) {
        header("Location: caja.php");
        exit;
    }

    $mesa_id = (int)$resOrden['id_mesa'];
    $estadoOrden = $resOrden['estado'];

    if ($estadoOrden !== 'abierta') {
        header("Location: orden.php?origen=caja&id=" . urlencode($orden_id));
        exit;
    }
} else {
    $mesa_id = $idRecibido;

    $stmtOrden = $conexion->prepare("
        SELECT id, estado
        FROM orden
        WHERE id_mesa = ? AND estado = 'abierta'
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmtOrden->bind_param("i", $mesa_id);
    $stmtOrden->execute();
    $resOrden = $stmtOrden->get_result()->fetch_assoc();

    if (!$resOrden) {
        header("Location: orden.php?origen=mesa&id=" . urlencode($mesa_id));
        exit;
    }

    $orden_id = (int)$resOrden['id'];
    $estadoOrden = $resOrden['estado'];
}

if ($orden_id <= 0 || $mesa_id < 0) {
    header("Location: " . ($origen === 'caja' ? "caja.php" : "mesas.php"));
    exit;
}

$urlRetorno = "orden.php?origen=" . urlencode($origen) . "&id=" . urlencode($idRecibido);

/* Productos */
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
JOIN detalle_precio dp 
    ON dp.id_producto = p.id
ORDER BY p.id, dp.precio
";

$result = $conexion->query($sql);

$productos = [];
while ($row = $result->fetch_assoc()) {
    $id = $row['id'];

    if (!isset($productos[$id])) {
        $productos[$id] = [
            "nombre" => $row["nombre"],
            "descripcion" => $row["descripcion"],
            "categoria" => $row["categoria"],
            "estado" => $row["estado"],
            "precios" => []
        ];
    }

    $productos[$id]["precios"][] = [
        "id" => $row["id_precio"],
        "tamano" => $row["tamano"],
        "precio" => $row["precio"]
    ];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carta - Sebastiani POS</title>
    <link rel="stylesheet" href="src/output.css">
    <link rel="stylesheet" href="src/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700;800&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="h-dvh w-auto overflow-hidden flex text-sm lg:text-base">
    <main class="flex-1 flex flex-col h-full overflow-hidden relative">
        
        <header class="px-6 py-4 flex flex-col gap-4 items-center bg-[#F9F7F1] shrink-0">
            <div class="w-full max-w-4xl flex items-center justify-between">
                <div class="flex flex-col">
                    <h1 class="text-xl font-head font-extrabold text-[#2C2C2C]">Carta</h1>
                    <p class="text-gray-500 text-sm font-semibold">
                        <?php echo htmlspecialchars(textoMesa((int)$mesa_id)); ?> - Orden #<?php echo str_pad($orden_id, 4, "0", STR_PAD_LEFT); ?>
                    </p>
                </div>
                <a href="<?php echo htmlspecialchars($urlRetorno); ?>"
                   class="w-10 h-10 text-2xl rounded-full bg-white shadow-sm flex items-center justify-center text-[#A83232] hover:bg-[#A83232] hover:text-white transition-colors">
                    <i class="fa-solid fa-xmark"></i>
                </a>
            </div>

            <div class="relative w-full max-w-4xl flex items-center justify-between">
                <input id="buscador" type="text" placeholder="Buscar plato, pizza, bebida..." 
                       class="w-full pl-12 pr-4 py-3 bg-white border-none rounded-full shadow-sm text-gray-700 placeholder-gray-400 focus:ring-2 focus:ring-[#A83232] outline-none transition-all">
                <i class="fa-solid fa-magnifying-glass absolute left-5 top-1/2 -translate-y-1/2 text-gray-400"></i>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto px-6 pb-8 custom-scrollbar">
            <section>
                <div class="flex gap-3 mb-5 overflow-x-auto pb-2 scrollbar-hide">
                    <button class="btn-pill cat-btn px-4 py-2.5 font-head font-bold transition-all shrink-0 bg-[#2C2C2C] text-white shadow-lg"
                            data-cat="todo">
                        <i class="fa-solid fa-utensils mr-2"></i>Todo
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
                            $noDisp = ($prod["estado"] == "nodis");
                            $precioTexto = [];

                            foreach ($prod["precios"] as $p) {
                                $precioTexto[] = number_format($p["precio"], 0);
                            }

                            $precioStr = implode(" / ", $precioTexto);

                            $imgNombre = str_replace(" ", "_", $prod["nombre"]);
                            $imgPath = "img/" . $imgNombre . ".webp";
                        ?>

                        <div class="card-modern <?= $noDisp ? 'bg-gray-50 opacity-60 grayscale' : 'bg-white hover:shadow-soft hover:-translate-y-1 cursor-pointer' ?> p-4 relative group transition-all duration-300"
                             data-id="<?= $id ?>"
                             data-cat="<?= $prod["categoria"] ?>"
                             data-name="<?= strtolower(htmlspecialchars($prod["nombre"])) ?>">

                            <?php if ($noDisp): ?>
                                <div class="absolute inset-0 flex items-center justify-center z-10">
                                    <span class="bg-gray-800 text-white text-xs font-bold px-3 py-1 rounded-full">
                                        NO DISPONIBLE
                                    </span>
                                </div>
                            <?php endif; ?>

                            <div class="w-full h-25 rounded-2xl bg-gray-100 mb-3 overflow-hidden">
                                <img src="<?= $imgPath ?>"
                                     onerror="this.src='img/default.webp'"
                                     class="w-full h-full object-cover">
                            </div>

                            <h3 class="font-head font-bold text-[#2C2C2C] text-lg leading-tight mb-1">
                                <?= htmlspecialchars($prod["nombre"]) ?>
                            </h3>

                            <p class="text-xs text-gray-400 mb-3 truncate">
                                <?= htmlspecialchars($prod["descripcion"]) ?>
                            </p>

                            <div class="flex justify-between items-center">
                                <span class="font-head font-extrabold text-lg <?= $noDisp ? 'text-gray-400' : 'text-[#A83232]' ?>">
                                    S/. <?= $precioStr ?>
                                </span>

                                <?php if (!$noDisp): ?>
                                <button class="btn-add w-8 h-8 rounded-full bg-[#A83232] text-white flex items-center justify-center hover:bg-[#8a2525] shadow-md">
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>

        <div id="modalProducto"
             class="hidden absolute inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center">
            <div class="flex w-full max-w-md rounded-3xl bg-white flex-col m-4 p-5 pt-5 gap-4 relative">
                <div class="flex justify-between items-center">
                    <div class="min-w-0">
                        <h3 id="modalNombre" class="font-head font-bold text-[#2C2C2C] text-xl leading-tight mb-1">Americana</h3>
                        <p id="modalDesc" class="text-sm truncate">Queso y jamón.</p>
                    </div>
                    <button id="btnCerrarModal"
                            class="w-10 h-10 ml-5 text-2xl rounded-full bg-white shadow-sm flex items-center justify-center text-[#A83232] hover:bg-[#A83232] hover:text-white transition-colors">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="w-auto h-37 rounded-2xl bg-gray-100 overflow-hidden relative">
                    <img id="modalImg" src="img/4_Estaciones.webp" class="w-full h-full object-cover">
                </div>

                <div id="bloqueTamano" class="flex gap-2 items-center">
                    <span class="font-head font-extrabold text-[#A83232] text-base">Tamaño:</span>
                    <select id="selectTamano" class="p-2 bg-white border-none rounded-full shadow-sm font-head text-[#A83232] text-sm focus:ring-2 focus:ring-[#A83232] outline-none transition-all">
                        <option value="">Seleccione</option>
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
                            <option value="">Seleccione</option>
                        </select>
                    </div>
                </div>

                <textarea id="detallePedido" autocomplete="off" rows="2"
                          class="block w-full rounded-md px-3.5 py-2 text-base font-head text-[#A83232] outline-2 -outline-offset-1 outline-[#A83232]"
                          placeholder="Detalle del pedido"></textarea>

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
                        class="text-lg px-3 py-2 gap-3 rounded-full bg-[#A83232] text-white flex items-center justify-center hover:bg-[#8a2525] shadow-md transition-colors">
                    <i class="fa-solid fa-plus"></i>
                    <span class="font-head font-bold text-base">AGREGAR</span>
                </button>
            </div>
        </div> 
    </main>

    <script>
    const PRODUCTOS = <?= json_encode($productos, JSON_UNESCAPED_UNICODE) ?>;
    const ordenActual = <?= (int)$orden_id ?>;
    const origenActual = <?= json_encode($origen, JSON_UNESCAPED_UNICODE) ?>;
    const idActual = <?= json_encode($idRecibido, JSON_UNESCAPED_UNICODE) ?>;
    const urlRetorno = <?= json_encode($urlRetorno, JSON_UNESCAPED_UNICODE) ?>;

    const botones = document.querySelectorAll(".cat-btn");
    const cards = document.querySelectorAll(".card-modern");
    const buscador = document.getElementById("buscador");
    const modal = document.getElementById("modalProducto");
    const btnCerrarModal = document.getElementById("btnCerrarModal");
    const modalImg = document.getElementById("modalImg");
    const bloqueParteSabor = document.getElementById("bloqueParteSabor");
    const selectParte = document.getElementById("selectParte");
    const selectSabor = document.getElementById("selectSabor");
    const labelSabor = document.getElementById("labelSabor");
    const selectTamano = document.getElementById("selectTamano");
    const detallePedido = document.getElementById("detallePedido");
    const precioManual = document.getElementById("precioManual");
    const cantidadPedido = document.getElementById("cantidadPedido");

    let productoActual = null;
    let categoriaActiva = "todo";

    function aplicarFiltros() {
        const texto = buscador.value.toLowerCase();

        cards.forEach(card => {
            const cat = card.dataset.cat;
            const nombre = card.dataset.name;

            const matchTexto = nombre.includes(texto);
            const matchCat = categoriaActiva === "todo" || cat === categoriaActiva;

            card.style.display = (matchTexto && matchCat) ? "block" : "none";
        });
    }

    botones.forEach(btn => {
        btn.addEventListener("click", () => {
            categoriaActiva = btn.dataset.cat;

            botones.forEach(b => {
                b.classList.remove("bg-[#2C2C2C]", "text-white", "shadow-lg");
                b.classList.add("bg-white", "text-[#2C2C2C]", "shadow-sm", "border", "border-gray-100", "cursor-pointer");
            });

            btn.classList.remove("bg-white", "text-[#2C2C2C]", "shadow-sm", "border", "border-gray-100", "cursor-pointer");
            btn.classList.add("bg-[#2C2C2C]", "text-white", "shadow-lg");

            aplicarFiltros();
        });
    });

    buscador.addEventListener("input", aplicarFiltros);

    cards.forEach(card => {
        card.addEventListener("click", (e) => {
            if (e.target.closest(".btn-add")) return;
            if (card.classList.contains("grayscale")) return;

            const id = card.dataset.id;
            agregarProducto(id);
        });
    });

    document.querySelectorAll(".btn-add").forEach(btn => {
        btn.addEventListener("click", (e) => {
            e.stopPropagation();
            const card = btn.closest(".card-modern");
            const id = card.dataset.id;
            agregarProducto(id);
        });
    });

    function getImagenProducto(nombre) {
        const imgNombre = nombre.replaceAll(" ", "_");
        return "img/" + imgNombre + ".webp";
    }

    function getPizzas() {
        return Object.entries(PRODUCTOS).filter(([id, p]) => p.categoria === "pizza");
    }

    function getPrecioPorTamano(prod, tamanoIndex) {
        if (!prod.precios[tamanoIndex]) return prod.precios[0].precio;
        return prod.precios[tamanoIndex].precio;
    }

    function llenarSabores(tamanoIndex) {
        selectSabor.innerHTML = "";

        getPizzas().forEach(([id, p]) => {
            const precio = getPrecioPorTamano(p, tamanoIndex);
            const opt = document.createElement("option");
            opt.value = id;
            opt.textContent = `${p.nombre} - S/. ${precio}`;
            selectSabor.appendChild(opt);
        });
    }

    function actualizarParte() {
        if (selectParte.value === "Mitad") {
            labelSabor.style.display = "inline";
            selectSabor.style.display = "block";
        } else {
            labelSabor.style.display = "none";
            selectSabor.style.display = "none";
        }
    }

    function actualizarPrecioManual() {
        if (!productoActual) return;

        const cantidad = Math.max(1, parseInt(cantidadPedido.value || "1", 10));
        let precioUnitario;

        if (productoActual.precios.length === 1) {
            precioUnitario = parseFloat(productoActual.precios[0].precio);
            precioManual.value = (precioUnitario * cantidad).toFixed(2);
            return;
        }

        const precioTamano =
            parseFloat(productoActual.precios[selectTamano.selectedIndex]?.precio
            ?? productoActual.precios[0].precio);

        if (productoActual.categoria !== "pizza") {
            precioManual.value = (precioTamano * cantidad).toFixed(2);
            return;
        }

        if (selectParte.value === "Entera") {
            precioManual.value = (precioTamano * cantidad).toFixed(2);
            return;
        }

        const saborId = selectSabor.value;
        const saborProd = PRODUCTOS[saborId];

        if (!saborProd) {
            precioManual.value = (precioTamano * cantidad).toFixed(2);
            return;
        }

        const precioSabor =
            parseFloat(saborProd.precios[selectTamano.selectedIndex]?.precio
            ?? saborProd.precios[0].precio);

        precioManual.value = (Math.max(precioTamano, precioSabor) * cantidad).toFixed(2);
    }

    function abrirModal() {
        modal.classList.remove("hidden");
        modal.classList.add("flex");
        actualizarParte();
    }

    function cerrarModal() {
        modal.classList.add("hidden");
        modal.classList.remove("flex");

        detallePedido.value = "";
        precioManual.value = "";
        cantidadPedido.value = "1";
        selectParte.value = "Entera";
        selectSabor.innerHTML = "";
        selectTamano.innerHTML = "";
        productoActual = null;
    }

    function agregarProducto(id) {
        const prod = PRODUCTOS[id];
        if (!prod) return;

        productoActual = prod;

        document.getElementById("modalNombre").textContent = prod.nombre;

        const descEl = document.getElementById("modalDesc");
        if (prod.descripcion && prod.descripcion.trim() !== "") {
            descEl.textContent = prod.descripcion;
            descEl.style.display = "block";
        } else {
            descEl.style.display = "none";
        }

        modalImg.src = getImagenProducto(prod.nombre);
        modalImg.onerror = () => modalImg.src = "img/default.webp";

        const bloqueTamano = document.getElementById("bloqueTamano");

        if (prod.categoria === "pizza") {
            bloqueParteSabor.style.display = "flex";
        } else {
            bloqueParteSabor.style.display = "none";
        }

        selectTamano.innerHTML = "";

        if (prod.precios.length > 1) {
            bloqueTamano.style.display = "flex";

            prod.precios.forEach((p, i) => {
                const opt = document.createElement("option");
                opt.value = p.id;
                opt.textContent = `${p.tamano} — S/. ${p.precio}`;
                if (i === 0) opt.selected = true;
                selectTamano.appendChild(opt);
            });
        } else {
            bloqueTamano.style.display = "none";
        }

        if (prod.categoria === "pizza") {
            llenarSabores(selectTamano.selectedIndex);
        }

        selectParte.value = "Entera";
        actualizarParte();
        actualizarPrecioManual();
        abrirModal();
    }

    selectTamano.addEventListener("change", () => {
        if (!productoActual) return;

        if (productoActual.categoria === "pizza") {
            llenarSabores(selectTamano.selectedIndex);
        }

        actualizarPrecioManual();
    });

    selectParte.addEventListener("change", () => {
        actualizarParte();
        actualizarPrecioManual();
    });

    selectSabor.addEventListener("change", actualizarPrecioManual);

    btnCerrarModal.addEventListener("click", cerrarModal);

    document.getElementById("btnGuardarPedido").addEventListener("click", async () => {
        if (!productoActual) return;

        const detalle = detallePedido.value.trim();
        const precio = parseFloat(precioManual.value || "0");
        const cantidad = Math.max(1, parseInt(cantidadPedido.value || "1", 10));

        const idPrecioSeleccionado =
            productoActual.precios.length > 1
                ? productoActual.precios[selectTamano.selectedIndex].id
                : productoActual.precios[0].id;

        const esMitad =
            productoActual.categoria === "pizza" &&
            selectParte.value === "Mitad";

        let idSegundoProducto = null;

        if (esMitad) {
            idSegundoProducto = selectSabor.value;
        }

        const response = await fetch("guardar_pedido.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                orden_id: ordenActual,
                id_precio: idPrecioSeleccionado,
                detalle: detalle,
                precio: precio,
                cantidad: cantidad,
                mitad: esMitad,
                id_segundo_producto: idSegundoProducto,
                origen: origenActual,
                id_retorno: idActual
            })
        });

        const result = await response.json().catch(() => null);

        if (response.ok && result && result.success) {
            window.location.href = urlRetorno;
        } else {
            alert("Error al guardar el pedido");
        }
    });

    cantidadPedido.addEventListener("input", actualizarPrecioManual);

    window.addEventListener("load", () => {
        detallePedido.value = "";
    });
    </script>
</body>
</html>
