<?php
require_once __DIR__ . "/../config/db.php";

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

$productos = [];
$selectIncremento = $hasIncremento ? ", p.incremento_minimo" : "";
$queryProductos = "SELECT p.id, p.nombre, p.descripcion, p.imagen_url, p.$precioColumn AS precio, c.nombre AS categoria, pu.max_puja$selectIncremento FROM productos p LEFT JOIN categorias c ON p.categoria_id = c.id LEFT JOIN (SELECT producto_id, MAX(monto_puja) AS max_puja FROM pujas GROUP BY producto_id) pu ON p.id = pu.producto_id WHERE p.estado = 'activo' ORDER BY p.id DESC";
$resultProductos = $mysqli->query($queryProductos);
if ($resultProductos) {
    while ($row = $resultProductos->fetch_assoc()) {
        $productos[] = $row;
    }
}

$pujas = [];
$queryPujas = "SELECT p.nombre AS producto, pu.nombre_usuario, pu.monto_puja, pu.fecha_puja FROM pujas pu INNER JOIN productos p ON p.id = pu.producto_id WHERE p.estado = 'activo' ORDER BY pu.fecha_puja DESC LIMIT 10";
$resultPujas = $mysqli->query($queryPujas);
if ($resultPujas) {
    while ($row = $resultPujas->fetch_assoc()) {
        $pujas[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Pantalla de Subasta</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
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
    </script>
</head>
<body class="bg-background-light text-slate-800">
    <header class="sticky top-0 z-40 bg-white/90 backdrop-blur border-b border-slate-100">
        <div class="container mx-auto px-8 py-4 flex items-center justify-between">
            <div>
                <div class="text-xs uppercase tracking-[0.3em] text-slate-400">Pantalla de subasta</div>
                <h1 class="text-3xl font-bold text-slate-900">En vivo</h1>
            </div>
            <div class="text-sm text-slate-500">Actualiza la pagina para ver nuevas pujas</div>
        </div>
    </header>

    <main class="container mx-auto px-8 py-8 grid grid-cols-1 xl:grid-cols-[2fr_1fr] gap-8">
        <section class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php foreach ($productos as $producto) { ?>
                <?php
                    $img = $producto["imagen_url"] ?? "";
                    if ($img !== "" && $img[0] !== "/" && !preg_match("~^https?://~", $img)) {
                        $img = "../" . $img;
                    }
                    $precioActual = (float) ($producto["precio"] ?? 0);
                    $maxPuja = (float) ($producto["max_puja"] ?? 0);
                    if ($maxPuja > $precioActual) {
                        $precioActual = $maxPuja;
                    }
                    $incremento = $hasIncremento ? (float) ($producto["incremento_minimo"] ?? 0) : 0.0;
                ?>
                <article class="bg-white rounded-3xl shadow-md border border-slate-100 overflow-hidden flex flex-col">
                    <div class="relative h-56 overflow-hidden">
                        <?php if ($img !== "") { ?>
                            <img class="w-full h-full object-cover" src="<?php echo htmlspecialchars($img); ?>" alt="" />
                        <?php } ?>
                        <div class="absolute top-4 left-4 bg-white/90 px-3 py-1 rounded-full text-xs font-bold text-primary">
                            <?php echo htmlspecialchars($producto["categoria"] ?? ""); ?>
                        </div>
                    </div>
                    <div class="p-5 flex flex-col gap-3">
                        <h2 class="text-xl font-bold text-slate-900"><?php echo htmlspecialchars($producto["nombre"] ?? ""); ?></h2>
                        <p class="text-sm text-slate-500 line-clamp-2"><?php echo htmlspecialchars($producto["descripcion"] ?? ""); ?></p>
                        <div class="flex items-end justify-between">
                            <div>
                                <div class="text-xs uppercase text-slate-400 font-semibold">Puja actual</div>
                                <div class="text-2xl font-bold text-secondary">$<?php echo number_format($precioActual, 2); ?></div>
                                <?php if ($incremento > 0) { ?>
                                    <div class="text-xs text-slate-400">Incremento: $<?php echo number_format($incremento, 2); ?></div>
                                <?php } ?>
                            </div>
                            <div class="text-xs text-slate-400">En subasta</div>
                        </div>
                    </div>
                </article>
            <?php } ?>
        </section>

        <aside class="bg-white rounded-3xl shadow-md border border-slate-100 p-6">
            <div class="text-xs uppercase tracking-[0.3em] text-slate-400">Ultimas pujas</div>
            <h3 class="text-2xl font-bold text-slate-900 mt-2">Actividad reciente</h3>
            <div class="mt-6 space-y-4">
                <?php if (count($pujas) === 0) { ?>
                    <div class="text-sm text-slate-500">Aun no hay pujas registradas.</div>
                <?php } else { ?>
                    <?php foreach ($pujas as $puja) { ?>
                        <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-3">
                            <div>
                                <div class="text-sm font-semibold text-slate-800"><?php echo htmlspecialchars($puja["producto"] ?? ""); ?></div>
                                <div class="text-xs text-slate-400"><?php echo htmlspecialchars($puja["nombre_usuario"] ?? ""); ?></div>
                            </div>
                            <div class="text-lg font-bold text-secondary">$<?php echo number_format((float) ($puja["monto_puja"] ?? 0), 2); ?></div>
                        </div>
                    <?php } ?>
                <?php } ?>
            </div>
        </aside>
    </main>
</body>
</html>
