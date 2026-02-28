<?php
require_once __DIR__ . "/../config/db.php";

$id = (int) ($_GET["id"] ?? 0);
$categoriaFiltro = (int) ($_GET["categoria"] ?? 0);
$status = trim($_GET["status"] ?? "");

if ($id <= 0) {
    http_response_code(400);
    exit("Producto invalido.");
}

$precioColumn = "predcio_inicial";
$checkPrecio = $mysqli->query("SHOW COLUMNS FROM productos LIKE 'predcio_inicial'");
if ($checkPrecio && $checkPrecio->num_rows === 0) {
    $checkPrecioAlt = $mysqli->query("SHOW COLUMNS FROM productos LIKE 'precio_inicial'");
    if ($checkPrecioAlt && $checkPrecioAlt->num_rows > 0) {
        $precioColumn = "precio_inicial";
    }
}

$hasIncremento = false;
$checkInc = $mysqli->query("SHOW COLUMNS FROM productos LIKE 'incremento_minimo'");
if ($checkInc && $checkInc->num_rows > 0) {
    $hasIncremento = true;
}

$hasInicio = false;
$checkInicio = $mysqli->query("SHOW COLUMNS FROM productos LIKE 'fecha_inicio'");
if ($checkInicio && $checkInicio->num_rows > 0) {
    $hasInicio = true;
}

$hasFin = false;
$checkFin = $mysqli->query("SHOW COLUMNS FROM productos LIKE 'fecha_fin'");
if ($checkFin && $checkFin->num_rows > 0) {
    $hasFin = true;
}


$hasOrigen = false;
$checkOrigen = $mysqli->query("SHOW COLUMNS FROM pujas LIKE 'origen'");
if ($checkOrigen && $checkOrigen->num_rows > 0) {
    $hasOrigen = true;
}

$producto = null;
$selectIncremento = $hasIncremento ? ", p.incremento_minimo" : "";
$selectInicio = $hasInicio ? ", p.fecha_inicio" : "";
$selectFin = $hasFin ? ", p.fecha_fin" : "";
$stmt = $mysqli->prepare("SELECT p.id, p.nombre, p.descripcion, p.imagen_url, p.estado, p.$precioColumn AS precio, c.nombre AS categoria$selectIncremento$selectInicio$selectFin FROM productos p LEFT JOIN categorias c ON p.categoria_id = c.id WHERE p.id = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $producto = $result ? $result->fetch_assoc() : null;
    $stmt->close();
}

if (!$producto) {
    http_response_code(404);
    exit("Producto no encontrado.");
}

$maxPuja = 0.0;
$stmtMax = $mysqli->prepare("SELECT MAX(monto_puja) AS max_puja FROM pujas WHERE producto_id = ?");
if ($stmtMax) {
    $stmtMax->bind_param("i", $id);
    $stmtMax->execute();
    $result = $stmtMax->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmtMax->close();
    if ($row && $row["max_puja"] !== null) {
        $maxPuja = (float) $row["max_puja"];
    }
}

$precioActual = (float) ($producto["precio"] ?? 0);
if ($maxPuja > $precioActual) {
    $precioActual = $maxPuja;
}

$incremento = $hasIncremento ? (float) ($producto["incremento_minimo"] ?? 0) : 0.0;
$minimo = $precioActual + $incremento;

$inicio = $hasInicio && !empty($producto["fecha_inicio"]) ? new DateTime($producto["fecha_inicio"]) : null;
$fin = $hasFin && !empty($producto["fecha_fin"]) ? new DateTime($producto["fecha_fin"]) : null;
$ahora = new DateTime();
$activoTiempo = ($inicio === null || $ahora >= $inicio) && ($fin === null || $ahora <= $fin);
$estadoActual = ($producto["estado"] ?? "activo") === "activo" && $activoTiempo;

$pujas = [];
$selectOrigen = $hasOrigen ? ", origen" : "";
$stmtPujas = $mysqli->prepare("SELECT nombre_usuario, monto_puja, fecha_puja$selectOrigen FROM pujas WHERE producto_id = ? ORDER BY fecha_puja DESC LIMIT 20");
if ($stmtPujas) {
    $stmtPujas->bind_param("i", $id);
    $stmtPujas->execute();
    $result = $stmtPujas->get_result();
    while ($row = $result->fetch_assoc()) {
        $pujas[] = $row;
    }
    $stmtPujas->close();
}

$img = $producto["imagen_url"] ?? "";
if ($img !== "" && $img[0] !== "/" && !preg_match("~^https?://~", $img)) {
    $img = "../" . $img;
}

$volver = "subasta.php";
if ($categoriaFiltro > 0) {
    $volver .= "?categoria=" . $categoriaFiltro;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title><?php echo htmlspecialchars($producto["nombre"] ?? "Producto"); ?></title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet" />
    <link href="../css/subasta.css" rel="stylesheet" />
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        primary: "#FF91AF",
                        secondary: "#00B4FF",
                        "background-light": "#F8F9FA",
                        "background-dark": "#121212",
                    },
                    fontFamily: {
                        display: ["Outfit", "sans-serif"],
                    },
                    borderRadius: {
                        DEFAULT: "12px",
                    },
                },
            },
        };

        const REFRESH_MS = 12000;
        setInterval(() => {
            const active = document.activeElement;
            if (active && ["INPUT", "TEXTAREA", "SELECT"].includes(active.tagName)) {
                return;
            }
            window.location.reload();
        }, REFRESH_MS);
    </script>
</head>
<body class="bg-background-light dark:bg-background-dark text-slate-800 dark:text-slate-200">
    <header class="bg-white dark:bg-slate-900 border-b border-slate-100 dark:border-slate-800 sticky top-0 z-50">
        <div class="container mx-auto px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <a class="text-slate-500 hover:text-primary" href="<?php echo htmlspecialchars($volver); ?>">&larr; Volver</a>
                <div>
                    <div class="text-xs uppercase tracking-widest text-slate-400">Producto</div>
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($producto["nombre"] ?? ""); ?></h1>
                </div>
            </div>
            <div class="text-sm text-slate-500">Categoria: <?php echo htmlspecialchars($producto["categoria"] ?? ""); ?></div>
        </div>
    </header>

    <main class="container mx-auto px-6 py-10 grid grid-cols-1 lg:grid-cols-[1.2fr_0.8fr] gap-10">
        <section class="bg-white dark:bg-slate-900 rounded-3xl shadow-md border border-slate-100 dark:border-slate-800 overflow-hidden">
            <?php if ($img !== "") { ?>
                <img class="w-full h-96 object-cover" src="<?php echo htmlspecialchars($img); ?>" alt="" />
            <?php } ?>
            <div class="p-6 grid gap-4">
                <p class="text-slate-500 leading-relaxed"><?php echo htmlspecialchars($producto["descripcion"] ?? ""); ?></p>
                <div class="flex flex-wrap gap-6">
                    <div>
                        <div class="text-xs uppercase text-slate-400 font-semibold">Puja actual</div>
                        <div class="text-3xl font-bold text-secondary">MXN $<?php echo number_format($precioActual, 2); ?></div>
                    </div>
                    <?php if ($incremento > 0) { ?>
                        <div>
                            <div class="text-xs uppercase text-slate-400 font-semibold">Minimo</div>
                            <div class="text-lg font-semibold text-slate-700 dark:text-slate-200">$<?php echo number_format($minimo, 2); ?></div>
                        </div>
                    <?php } ?>
                </div>
                <?php if (!$estadoActual) { ?>
                    <div class="bg-slate-100 dark:bg-slate-800/60 text-slate-500 rounded-2xl px-4 py-3 text-sm">
                        <?php if (($producto["estado"] ?? "") === "pausado") { ?>
                            Esta subasta esta pausada.
                        <?php } elseif ($fin !== null && $ahora > $fin) { ?>
                            Esta subasta esta finalizada.
                        <?php } elseif ($inicio !== null && $ahora < $inicio) { ?>
                            Esta subasta inicia el <?php echo htmlspecialchars($inicio->format("d/m/Y H:i")); ?>.
                        <?php } else { ?>
                            Esta subasta no esta disponible.
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>
        </section>

        <aside class="bg-white dark:bg-slate-900 rounded-3xl shadow-md border border-slate-100 dark:border-slate-800 p-6 grid gap-6">
            <div>
                <div class="text-xs uppercase tracking-widest text-slate-400">Pujar</div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Participa</h2>
            </div>
            <?php if ($status === "ok") { ?>
                <div class="bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-2xl px-4 py-3">
                    Tu oferta sea realizado
                </div>
            <?php } elseif ($status === "error") { ?>
                <div class="bg-rose-50 text-rose-700 border border-rose-200 rounded-2xl px-4 py-3">
                    No se pudo registrar la puja. Verifica el monto e intenta de nuevo.
                </div>
            <?php } ?>
            <?php if ($estadoActual) { ?>
                <form class="grid gap-3" action="pujar.php" method="post">
                    <input type="hidden" name="producto_id" value="<?php echo (int) $producto["id"]; ?>" />
                    <input type="hidden" name="categoria" value="<?php echo (int) $categoriaFiltro; ?>" />
                    <input class="bg-slate-50 dark:bg-slate-800 border-none rounded-lg text-sm px-3 py-2 focus:ring-primary" name="nombre_usuario" required placeholder="Nombre" type="text" />
                    <input class="bg-slate-50 dark:bg-slate-800 border-none rounded-lg text-sm px-3 py-2 focus:ring-primary" name="correo_usuario" required placeholder="Correo" type="email" />
                    <input class="bg-slate-50 dark:bg-slate-800 border-none rounded-lg text-sm px-3 py-2 focus:ring-primary" name="telefono_usuario" required placeholder="Telefono" type="tel" />
                    <input class="bg-slate-50 dark:bg-slate-800 border-none rounded-lg text-sm px-3 py-2 focus:ring-primary" name="monto_puja" required min="<?php echo number_format($minimo, 2, '.', ''); ?>" step="0.01" placeholder="Monto" type="number" />
                    <button class="bg-primary text-white px-5 py-2.5 rounded-xl font-bold text-sm hover:shadow-lg hover:shadow-primary/30 transition-all" type="submit">
                        Pujar
                    </button>
                </form>
            <?php } ?>

            <div>
                <div class="text-xs uppercase tracking-widest text-slate-400">Historial</div>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Pujas recientes</h3>
                <div class="mt-3 space-y-2 text-sm">
                    <?php if (count($pujas) === 0) { ?>
                        <div class="text-slate-500">Aun no hay pujas.</div>
                    <?php } else { ?>
                        <?php foreach ($pujas as $puja) { ?>
                            <div class="flex items-center justify-between">
                                <span class="truncate max-w-[60%]"><?php echo htmlspecialchars($puja["nombre_usuario"] ?? ""); ?></span>
                                <span class="font-semibold">$<?php echo number_format((float) ($puja["monto_puja"] ?? 0), 2); ?></span>
                            </div>
                            <?php if ($hasOrigen && !empty($puja["origen"])) { ?>
                                <div class="text-[10px] uppercase tracking-widest text-slate-400">
                                    <?php echo htmlspecialchars($puja["origen"]); ?>
                                </div>
                            <?php } ?>
                        <?php } ?>
                    <?php } ?>
                </div>
            </div>
        </aside>
    </main>
</body>
</html>
