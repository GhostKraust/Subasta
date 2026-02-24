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

$categorias = [];
$resultCategorias = $mysqli->query("SELECT id, nombre FROM categorias ORDER BY nombre ASC");
if ($resultCategorias) {
    while ($row = $resultCategorias->fetch_assoc()) {
        $categorias[] = $row;
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

$monedas = ["MXN", "USD", "CAD"];
$rates = ["MXN" => 1.0, "USD" => 1.0, "CAD" => 1.0];
$ratesResult = $mysqli->query("SELECT moneda, tasa FROM exchange_rates");
if ($ratesResult) {
    while ($row = $ratesResult->fetch_assoc()) {
        $code = strtoupper($row["moneda"] ?? "");
        if (isset($rates[$code])) {
            $rates[$code] = (float) $row["tasa"];
        }
    }
}

function convertAmount($amountMXN, $currency, $rates)
{
    $rate = $rates[$currency] ?? 1.0;
    if ($currency === "MXN" || $rate <= 0) {
        $value = (float) $amountMXN;
    } else {
        $value = (float) $amountMXN / $rate;
    }

    return round($value, 0, PHP_ROUND_HALF_UP);
}

function formatCurrency($amountMXN, $currency, $rates)
{
    $value = convertAmount($amountMXN, $currency, $rates);
    $prefix = "$";
    if ($currency === "USD") {
        $prefix = "US$";
    } elseif ($currency === "CAD") {
        $prefix = "CA$";
    }

    return $prefix . number_format($value, 0);
}

$categoriaFiltro = (int) ($_GET["categoria"] ?? 0);
$status = trim($_GET["status"] ?? "");

$productos = [];
$selectIncremento = $hasIncremento ? ", p.incremento_minimo" : "";
$selectInicio = $hasInicio ? ", p.fecha_inicio" : "";
$selectFin = $hasFin ? ", p.fecha_fin" : "";
$queryProductos = "SELECT p.id, p.nombre, p.descripcion, p.imagen_url, p.estado, p.$precioColumn AS precio, c.nombre AS categoria, pu.max_puja$selectIncremento$selectInicio$selectFin FROM productos p LEFT JOIN categorias c ON p.categoria_id = c.id LEFT JOIN (SELECT producto_id, MAX(monto_puja) AS max_puja FROM pujas GROUP BY producto_id) pu ON p.id = pu.producto_id WHERE p.estado IN ('activo','finalizado')";
if ($hasFin) {
    $queryProductos .= " AND (p.fecha_fin IS NULL OR p.fecha_fin >= DATE_SUB(NOW(), INTERVAL 2 DAY))";
}
if ($categoriaFiltro > 0) {
    $queryProductos .= " AND p.categoria_id = " . $categoriaFiltro;
}
$queryProductos .= " ORDER BY p.id DESC";
$resultProductos = $mysqli->query($queryProductos);
if ($resultProductos) {
    while ($row = $resultProductos->fetch_assoc()) {
        $productos[] = $row;
    }
}

?>
<!DOCTYPE html>
<html lang="es"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Charity Auction - Pasitos de Luz / Casa Connor</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet"/>
<link href="../css/subasta.css" rel="stylesheet"/>
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
<body class="bg-background-light dark:bg-background-dark text-slate-800 dark:text-slate-200">
<div class="bg-primary text-white py-2 px-6 flex justify-between items-center text-sm font-medium">
<div class="flex items-center gap-2">
<span class="material-icons text-lg">phone</span>
<span>Contáctanos: (+52 322 135 6302)</span>
</div>
<div class="flex items-center gap-3">
<a class="bg-secondary p-1.5 rounded-full flex items-center justify-center hover:opacity-90 transition-opacity" href="#">
<img alt="Phone" class="w-4 h-4" src="https://lh3.googleusercontent.com/aida-public/AB6AXuBxFK45cv0gaRJ0xZfuzT2Bwa3-JMqXwpo6c1aSAZEpwS6Qn3yAD_n3oxqefRhD8fu3YfdX5UbL9_UkS5-goiF3Y5xUu3m-GA0MX3LXtnKgeMAhvzdqNXJYRwRsQGI88Ntl_q-1wG_fi5MqsGLPbbG3seFXUmO4VT9oH8AWL5IP2k7Di97UQnG62KawAIzhhNPWCR2XSL3mGIacTHEQZiLOUYsSCvWowpx6qfMPL6PEuakqq0JWr9wSdDMUH68R18Z4jHplsE3Kdw"/>
</a>
<a class="bg-secondary p-1.5 rounded-full flex items-center justify-center hover:opacity-90 transition-opacity" href="#">
<img alt="WhatsApp" class="w-4 h-4 invert" src="https://lh3.googleusercontent.com/aida-public/AB6AXuDcoz0Fs0k8pbdKqr-zOgO4uyrQ5mQg45wNx5zH-kXHTJMPYw9WmKR9rskpONpsNpdZcddx8dQlcHKGR_EIh2ZZWJg-ySr67SOu_92x8aBpkeMCQ_NacvCuW8FaesN9jxpqCm2KL3CHFnRJv79X4ZyODEug3J0JvjT2iiXBPJ5SxfHe9ONmggul-vlvu-vr8PNWSwTSC5KztbOgbWqE6p8u9AbtXjI8bh99Ffa0o73MPKfHXqnRPh_7mVFxHSP1tm75JmoZ-0vUmQ"/>
</a>
<a class="bg-secondary p-1.5 rounded-full flex items-center justify-center hover:opacity-90 transition-opacity" href="#">
<span class="material-icons text-base">location_on</span>
</a>
<a class="bg-secondary p-1.5 rounded-full flex items-center justify-center hover:opacity-90 transition-opacity" href="#">
<span class="material-icons text-base">facebook</span>
</a>
<a class="bg-secondary p-1.5 rounded-full flex items-center justify-center hover:opacity-90 transition-opacity" href="#">
<img alt="Instagram" class="w-4 h-4" src="https://lh3.googleusercontent.com/aida-public/AB6AXuCNKJ4nyRBz5Y6Od4tNvjINNEYQvla18pf7WhlWdXeX2n2kIAcS_cVIWgLTypG7jJqXjBWK45Gzs0jSX-TGlh2uklBVZNCNIGAESIxvDliJ4dLh40bjaLQ6P5hSCAm5eyqd1gBGL-WoThj0XDtIJpsfxbEo5ND4CEbbJdJETOW5sw1nM0P6HQ8OY6qzprzan9b8P_2urNcc1utB3zNz2Pa-EW5HPC3TQDFLmSr6sN0eva7XY4H0yIXsrx9dV3zoTTfK3tft53jObA"/>
</a>
<a class="bg-secondary p-1.5 rounded-full flex items-center justify-center hover:opacity-90 transition-opacity" href="#">
<span class="material-icons text-base">play_circle_filled</span>
</a>
</div>
</div>
<header class="bg-white dark:bg-slate-900 border-b border-slate-100 dark:border-slate-800 sticky top-0 z-50">
<div class="container mx-auto px-6 py-4 flex items-center justify-between">
<div class="flex items-center gap-6">
<div class="flex items-center gap-2">
<img alt="Pasitos de Luz Logo" class="h-16" src="https://lh3.googleusercontent.com/aida-public/AB6AXuCJBgXL6W54AfQQBLrEdcbUPl23gcyBOIkv6x7ZaxvFtMPkqIpiS6C2ZwR3ibXnMl5AR6fWT5-HX3hTc3CIRXT6ABbcQK2jXn2l1EW9gcNuG2eGieN2PCY45FSbADypP9YjkbeEeG42QsF-ZnNlckv76qCFlmHFUzLOk0a7wA3iTKPbNeqzPSZjeqDWl_bKYDyfgbuVjfu6-bv4bjXQsEKVOfayOwCHzrMI-5H8-XTZDkC1VDNOQAbzzR6KAOsuxpuLWdr2bmo9GQ"/>
<img alt="Casa Connor Logo" class="h-16" src="https://lh3.googleusercontent.com/aida-public/AB6AXuD6A6G-G67IViN5Qj5YTdPhmIDLmDu0mfHPTBr5jZqgnKT6WMMj0rC4S_u4wuFtPgMAM0tTdL2rrLp1e-ZXCTp6CkI143bR24dysrIpt0_8KVzzgBs9PJZXsviv_KRB2byA5wgSJ6CUb8sG0RAfEG8BJWstiHAo1LMJ8LrQwTs9weBZNfb7h53e9BfvkIdD1nG-EBAnzbKuKN-L_onEsV8UFuRxOu_MokwgJiE4DSv-tsBjYGxpDf8HgSJl6-BxozT2kRMAFH5kvQ"/>
</div>
<nav class="hidden lg:flex items-center gap-8 ml-8 font-semibold text-slate-700 dark:text-slate-300">
<a class="nav-item text-primary" href="#">Home</a>
<div class="nav-item flex items-center gap-1 cursor-pointer">About Us <span class="material-icons text-sm">expand_more</span></div>
<div class="nav-item flex items-center gap-1 cursor-pointer">Our Services <span class="material-icons text-sm">expand_more</span></div>
<a class="nav-item" href="#">Meet Our Kids</a>
<div class="nav-item flex items-center gap-1 cursor-pointer">Donate <span class="material-icons text-sm">expand_more</span></div>
<a class="nav-item" href="#">Campaigns</a>
<a class="nav-item" href="#">Events</a>
<div class="flex items-center gap-1 ml-4 border rounded px-2 py-1 border-slate-200 dark:border-slate-700">
<span class="text-lg">🇲🇽</span>
<span class="material-icons text-sm">expand_more</span>
</div>
</nav>
</div>
<button class="bg-primary hover:bg-[#ff7ca2] text-white px-6 py-3 rounded-full font-bold flex items-center gap-2 shadow-lg transition-all active:scale-95">
                DONATE <span class="material-icons text-base">favorite</span>
</button>
</div>
</header>
<section class="bg-slate-50 dark:bg-slate-800/50 py-12 px-6">
<div class="container mx-auto text-center">
<h1 class="text-4xl md:text-5xl font-bold text-slate-900 dark:text-white mb-4">Charity Auction</h1>
<p class="text-lg text-slate-600 dark:text-slate-400 max-w-2xl mx-auto">
                Support our mission to provide therapeutic and educational services to children with disabilities. 
                Bid on exclusive items and make a difference today.
            </p>
</div>
</section>
<?php if ($status === "ok") { ?>
<div class="container mx-auto px-6 mt-6">
    <div class="bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-2xl px-6 py-3">
        Tu puja fue registrada correctamente.
    </div>
</div>
<?php } elseif ($status === "error") { ?>
<div class="container mx-auto px-6 mt-6">
    <div class="bg-rose-50 text-rose-700 border border-rose-200 rounded-2xl px-6 py-3">
        No se pudo registrar la puja. Verifica los datos e intenta de nuevo.
    </div>
</div>
<?php } ?>
<div class="container mx-auto px-6 mt-8">
<div class="flex flex-wrap items-center justify-between gap-4 bg-white dark:bg-slate-900 p-4 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800">
<div class="flex items-center gap-4">
<span class="font-medium text-slate-500">Filter by:</span>
<form method="get">
<select name="categoria" class="bg-slate-50 dark:bg-slate-800 border-none rounded-lg text-sm px-4 py-2 focus:ring-primary" onchange="this.form.submit()">
<option value="0">Todas las categorias</option>
<?php foreach ($categorias as $categoria) { ?>
<option value="<?php echo (int) $categoria["id"]; ?>" <?php echo ((int) $categoriaFiltro === (int) $categoria["id"]) ? "selected" : ""; ?>>
<?php echo htmlspecialchars($categoria["nombre"]); ?>
</option>
<?php } ?>
</select>
</form>
<select class="bg-slate-50 dark:bg-slate-800 border-none rounded-lg text-sm px-4 py-2 focus:ring-primary">
<option>Price: Low to High</option>
<option>Price: High to Low</option>
<option>Ending Soon</option>
</select>
</div>
<div class="relative w-full md:w-64">
<input class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-lg py-2 pl-4 pr-10 text-sm focus:ring-primary" placeholder="Search items..." type="text"/>
<span class="material-icons absolute right-3 top-2 text-slate-400">search</span>
</div>
</div>
</div>
<main class="container mx-auto px-6 py-12">
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
<?php if (count($productos) === 0) { ?>
    <div class="col-span-full bg-white dark:bg-slate-900 rounded-2xl p-6 text-center text-slate-500">
        Aun no hay productos activos en subasta.
    </div>
<?php } else { ?>
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
            $inicio = $hasInicio && !empty($producto["fecha_inicio"]) ? new DateTime($producto["fecha_inicio"]) : null;
            $fin = $hasFin && !empty($producto["fecha_fin"]) ? new DateTime($producto["fecha_fin"]) : null;
            $ahora = new DateTime();
            $activoTiempo = ($inicio === null || $ahora >= $inicio) && ($fin === null || $ahora <= $fin);
            $estadoActual = ($producto["estado"] ?? "activo") === "activo" && $activoTiempo;
            $statusBadge = "";
            if (!$estadoActual) {
                if ($fin !== null && $ahora > $fin) {
                    $statusBadge = "Finalizado";
                } elseif ($inicio !== null && $ahora < $inicio) {
                    $statusBadge = "Inicia pronto";
                } else {
                    $statusBadge = "No disponible";
                }
            }
        ?>
        <div class="auction-card bg-white dark:bg-slate-900 rounded-3xl overflow-hidden shadow-md border border-slate-100 dark:border-slate-800 flex flex-col">
            <div class="relative h-60 overflow-hidden">
                <?php if ($img !== "") { ?>
                    <img alt="<?php echo htmlspecialchars($producto["nombre"] ?? "Producto"); ?>" class="w-full h-full object-cover" src="<?php echo htmlspecialchars($img); ?>"/>
                <?php } ?>
                <div class="absolute top-4 right-4 bg-white/90 dark:bg-slate-800/90 backdrop-blur-sm px-3 py-1 rounded-full text-xs font-bold text-primary shadow-sm">
                    <?php echo htmlspecialchars($producto["categoria"] ?? ""); ?>
                </div>
                <?php if ($statusBadge !== "") { ?>
                    <div class="absolute top-4 left-4 bg-slate-900/80 text-white px-3 py-1 rounded-full text-xs font-semibold">
                        <?php echo htmlspecialchars($statusBadge); ?>
                    </div>
                <?php } ?>
            </div>
            <div class="p-6 flex flex-col flex-grow">
                <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-2 leading-tight">
                    <?php echo htmlspecialchars($producto["nombre"] ?? ""); ?>
                </h3>
                <p class="text-sm text-slate-500 mb-4 line-clamp-2">
                    <?php echo htmlspecialchars($producto["descripcion"] ?? ""); ?>
                </p>
                <div class="mt-auto pt-4 border-t border-slate-50 dark:border-slate-800 flex justify-between items-end">
                    <div>
                        <span class="text-xs text-slate-400 uppercase font-semibold">Puja actual</span>
                        <div class="currency-stack text-secondary">
                            <?php foreach ($monedas as $code) { ?>
                                <div class="currency-line">
                                    <?php echo htmlspecialchars($code . " " . formatCurrency($precioActual, $code, $rates)); ?>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                    <a class="bg-primary text-white px-5 py-2.5 rounded-xl font-bold text-sm hover:shadow-lg hover:shadow-primary/30 transition-all" href="producto.php?id=<?php echo (int) $producto["id"]; ?>&categoria=<?php echo (int) $categoriaFiltro; ?>">
                        <?php echo $estadoActual ? "Ver y pujar" : "Ver detalles"; ?>
                    </a>
                </div>
            </div>
        </div>
    <?php } ?>
<?php } ?>
</div>
<div class="mt-16 flex justify-center">
<button class="flex items-center gap-2 px-8 py-3 border-2 border-primary text-primary hover:bg-primary hover:text-white font-bold rounded-full transition-all">
                Load More Items <span class="material-icons">refresh</span>
</button>
</div>
</main>
<footer class="mt-20">
<div class="bg-primary text-white py-16 px-6">
<div class="container mx-auto">
<div class="flex flex-col items-center text-center mb-12">
<div class="flex items-center gap-4 mb-8">
<img alt="Pasitos Logo White" class="h-24 brightness-0 invert" src="https://lh3.googleusercontent.com/aida-public/AB6AXuAGphVm7IzCYq_5-dzIVFl_wIMzK6Yit00A9lPZfnwHS66074IjXQMyJ-REWOE0823ArKK6S6PJxtqt-2XTFo0n5VXL5J-G6GKT2P0SC3FCZqhgXjcA7GN2mHjenPQJFlpIYti7rfdtGWgvwUkC72z5f-cfBqMKzwMSJyryYnN0gaFIuPpdlmFZ0G2ANT_2Kti6_P-c3Xr2EEDBYAI2c3FYnGxayTws_Z1K3WZUtQOnXbmaZ1abQmkzOg9xVlTp0E3dIa2oU1BYKA"/>
<img alt="Casa Connor Logo White" class="h-24 brightness-0 invert" src="https://lh3.googleusercontent.com/aida-public/AB6AXuCMwITLdcOitGrym8xTgXWALeE4DgxR4wyXiSyZFRif-t-KS-3Z0Xzvh0ji9CnlMDbJo2uvUytx6ZOY14SchHTIFghUa5Csx00DoQqc21yT8lZwg3D8ddKODASlMvX1MrULEAOO0xtAqO_V_LjuoctNjrPlpwoKFcmry6MjQc9m_EgAo2vy-KDnNEwnFzlfR6Y0o4dy_6yGud0goPr61Ny4t4AeuMpdUPICuhacVv1BptrNL-SoSpFQMqfTHgINkD99NLEORHlJ2g"/>
</div>
<p class="max-w-3xl text-lg opacity-90 leading-relaxed font-light">
                        Pasitos de Luz is a civil association in Banderas Bay. It is a registered non-profit organization founded by mothers of disabled children to meet their therapeutic, psychological, nutritional, educational and basic needs.
                    </p>
<div class="mt-6 text-sm opacity-80">
                        Boulevard Federacion | Nayarit, Mexico | C.P. 63737<br/>
                        Tel: (+52) 322 135 6302 | info@pasitosdeluz.org
                    </div>
</div>
<div class="flex flex-col items-center mb-12">
<div class="flex items-center gap-3 mb-6">
<span class="material-icons text-4xl">mark_as_unread</span>
<h3 class="text-2xl font-bold tracking-tight uppercase">Join Our Newsletter</h3>
</div>
<div class="flex w-full max-w-md bg-white rounded-xl overflow-hidden shadow-xl">
<input class="flex-grow px-6 py-4 text-slate-800 border-none focus:ring-0" placeholder="Enter your email" type="email"/>
<button class="bg-slate-900 text-white px-8 font-bold uppercase text-sm hover:bg-slate-800 transition-colors">
                            Subscribe
                        </button>
</div>
<a class="mt-6 text-sm font-bold underline underline-offset-4 hover:opacity-80" href="#">PRIVACY POLICY</a>
</div>
<div class="flex justify-center gap-6 pb-8 border-b border-white/20">
<a class="hover:scale-110 transition-transform" href="#"><span class="material-icons">phone</span></a>
<a class="hover:scale-110 transition-transform" href="#"><span class="material-icons">chat</span></a>
<a class="hover:scale-110 transition-transform" href="#"><span class="material-icons">location_on</span></a>
<a class="hover:scale-110 transition-transform" href="#"><span class="material-icons">facebook</span></a>
<a class="hover:scale-110 transition-transform" href="#"><span class="material-icons">camera_alt</span></a>
<a class="hover:scale-110 transition-transform" href="#"><span class="material-icons">play_circle</span></a>
</div>
</div>
</div>
<div class="bg-white dark:bg-slate-950 py-12 px-6">
<div class="container mx-auto">
<div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center text-sm font-medium text-slate-600 dark:text-slate-400">
<div class="space-y-3">
<a class="block hover:text-primary transition-colors" href="#">About Us</a>
<a class="block hover:text-primary transition-colors" href="#">Pasitos de Luz History</a>
<a class="block hover:text-primary transition-colors" href="#">Casa Connor</a>
</div>
<div class="space-y-3">
<a class="block hover:text-primary transition-colors" href="#">Board of Directors</a>
<a class="block hover:text-primary transition-colors" href="#">Love Pasitos Monthly</a>
<a class="block hover:text-primary transition-colors" href="#">Volunteer</a>
</div>
<div class="space-y-3">
<a class="block hover:text-primary transition-colors" href="#">List of Necessities</a>
<a class="block hover:text-primary transition-colors" href="#">Financials</a>
<a class="block hover:text-primary transition-colors" href="#">Campaigns</a>
</div>
<div class="space-y-3">
<a class="block hover:text-primary transition-colors" href="#">Events</a>
<a class="block hover:text-primary transition-colors" href="#">News</a>
<a class="block hover:text-primary transition-colors" href="#">Frequently Asked Questions</a>
</div>
</div>
<div class="mt-12 text-center text-xs text-slate-400 border-t border-slate-100 dark:border-slate-900 pt-8">
                    © 2023 Pasitos de Luz / Casa Connor. All rights reserved.
                    <div class="mt-3">
                        <a class="font-semibold underline underline-offset-4 hover:text-primary" href="../php/login.php">Administracion: agregar productos</a>
                    </div>
                </div>
</div>
</div>
</footer>

</body></html>
