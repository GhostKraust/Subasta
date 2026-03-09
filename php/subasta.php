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
$ordenFiltro = trim($_GET["orden"] ?? "");
$busqueda = trim($_GET["q"] ?? "");
$status = trim($_GET["status"] ?? "");

$productos = [];
$selectIncremento = $hasIncremento ? ", p.incremento_minimo" : "";
$selectInicio = $hasInicio ? ", p.fecha_inicio" : "";
$selectFin = $hasFin ? ", p.fecha_fin" : "";
$queryProductos = "SELECT p.id, p.nombre, p.descripcion, p.imagen_url, p.estado, p.$precioColumn AS precio, c.nombre AS categoria, pu.max_puja$selectIncremento$selectInicio$selectFin FROM productos p LEFT JOIN categorias c ON p.categoria_id = c.id LEFT JOIN (SELECT producto_id, MAX(monto_puja) AS max_puja FROM pujas GROUP BY producto_id) pu ON p.id = pu.producto_id WHERE p.estado IN ('activo', 'pausado')";
if ($categoriaFiltro > 0) {
    $queryProductos .= " AND p.categoria_id = " . $categoriaFiltro;
}
if ($busqueda !== "") {
    $safeSearch = $mysqli->real_escape_string($busqueda);
    $queryProductos .= " AND (p.nombre LIKE '%" . $safeSearch . "%' OR p.descripcion LIKE '%" . $safeSearch . "%')";
}

$orderClause = " ORDER BY p.id DESC";
if ($ordenFiltro === "precio_asc") {
    $orderClause = " ORDER BY COALESCE(pu.max_puja, p.$precioColumn) ASC, p.id DESC";
} elseif ($ordenFiltro === "precio_desc") {
    $orderClause = " ORDER BY COALESCE(pu.max_puja, p.$precioColumn) DESC, p.id DESC";
} elseif ($ordenFiltro === "fin" && $hasFin) {
    $orderClause = " ORDER BY (p.fecha_fin IS NULL), p.fecha_fin ASC, p.id DESC";
}

$queryProductos .= $orderClause;
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
<title>Subasta-Pasitos de Luz-Casa Connor</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet"/>
<link href="../css/subasta.css?v=20260303" rel="stylesheet"/>
<script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        primary: "#f78da7",
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
<header>
    <div class="top-bar py-2 text-white px-4 d-flex justify-content-between align-items-center">
        <div class="fw-bold">
            <i class="bi bi-telephone-fill me-2"></i> Contáctanos: (+52 322 135 6302)
        </div>
        <div class="d-flex gap-2 fs-5">
            <a href="#" class="top-icon"><i class="bi bi-telephone"></i></a>
            <a href="#" class="top-icon"><i class="bi bi-whatsapp"></i></a>
            <a href="#" class="top-icon"><i class="bi bi-geo-alt-fill"></i></a>
            <a href="#" class="top-icon"><i class="bi bi-facebook"></i></a>
            <a href="#" class="top-icon"><i class="bi bi-instagram"></i></a>
            <a href="#" class="top-icon"><i class="bi bi-youtube"></i></a>
        </div>
    </div>

    <nav class="navbar navbar-expand-lg navbar-light bg-white py-3 shadow-sm">
        <div class="container-fluid px-4">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <img src="../Imagenes/logo_pasitos-removebg-preview.png" alt="Pasitos de Luz" class="me-2 logo-pasitos">
                <img src="../Imagenes/connor29.png" alt="Casa Connor" height="55">
            </a>

            <div class="navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto fw-semibold" style="font-size: 0.95rem;">
                    <li class="nav-item"><a class="nav-link active" href="#">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">About Us <i class="bi bi-chevron-down small"></i></a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Our Services <i class="bi bi-chevron-down small"></i></a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Meet Our Kids</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Donate <i class="bi bi-chevron-down small"></i></a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Campaigns <i class="bi bi-chevron-down small"></i></a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Other Ways to Help <i class="bi bi-chevron-down small"></i></a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Events</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Contact us <i class="bi bi-chevron-down small"></i></a></li>
                    <li class="nav-item d-flex align-items-center">
                        <a class="nav-link nav-flag" href="#" aria-label="Mexico">
                            <img src="https://flagcdn.com/w20/mx.png" alt="Mexico">
                        </a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <button class="btn btn-donar px-4 py-2 fw-bold shadow-sm">
                        DONAR <i class="bi bi-heart-fill ms-1"></i>
                    </button>
                </div>
            </div>
        </div>
    </nav>
</header>
<section class="bg-slate-50 dark:bg-slate-800/50 py-12 px-6">
<div class="container mx-auto text-center">
<h1 class="text-4xl md:text-5xl font-bold text-slate-900 dark:text-white mb-4"></h1>
<p class="text-lg text-slate-600 dark:text-slate-400 max-w-2xl mx-auto">
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
<form method="get" class="filter-bar flex flex-wrap items-center justify-between gap-4 bg-white dark:bg-slate-900 p-4 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800">
    <div class="flex items-center gap-4">
        <span class="font-medium text-slate-500">Filter by:</span>
        <select name="categoria" class="filter-control bg-slate-50 dark:bg-slate-800 border-none rounded-lg text-sm px-4 py-2 focus:ring-primary" onchange="this.form.submit()">
            <option value="0">Todas las categorias</option>
            <?php foreach ($categorias as $categoria) { ?>
                <option value="<?php echo (int) $categoria["id"]; ?>" <?php echo ((int) $categoriaFiltro === (int) $categoria["id"]) ? "selected" : ""; ?>>
                    <?php echo htmlspecialchars($categoria["nombre"]); ?>
                </option>
            <?php } ?>
        </select>
        <select name="orden" class="filter-control bg-slate-50 dark:bg-slate-800 border-none rounded-lg text-sm px-4 py-2 focus:ring-primary" onchange="this.form.submit()">
            <option value="">Ordenar</option>
            <option value="precio_asc" <?php echo $ordenFiltro === "precio_asc" ? "selected" : ""; ?>>Price: Low to High</option>
            <option value="precio_desc" <?php echo $ordenFiltro === "precio_desc" ? "selected" : ""; ?>>Price: High to Low</option>
            <option value="fin" <?php echo $ordenFiltro === "fin" ? "selected" : ""; ?>>Ending Soon</option>
        </select>
    </div>
    <div class="relative w-full md:w-64">
        <input name="q" value="<?php echo htmlspecialchars($busqueda); ?>" class="filter-control w-full bg-slate-50 dark:bg-slate-800 border-none rounded-lg py-2 pl-4 pr-10 text-sm focus:ring-primary" placeholder="Search items..." type="text"/>
        <span class="material-icons absolute right-3 top-2 text-slate-400">search</span>
    </div>
</form>
</div>
<main class="container mx-auto px-6 py-12">
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
<?php if (count($productos) === 0) { ?>
    <div class="col-span-full bg-white dark:bg-slate-900 rounded-2xl p-6 text-center text-slate-500">
        Aun no hay productos activos en subasta.
    </div>
<?php } else { ?>
    <?php
        $extraParams = [];
        if ($categoriaFiltro > 0) {
            $extraParams[] = "categoria=" . $categoriaFiltro;
        }
        if ($ordenFiltro !== "") {
            $extraParams[] = "orden=" . urlencode($ordenFiltro);
        }
        if ($busqueda !== "") {
            $extraParams[] = "q=" . urlencode($busqueda);
        }
        $extraQuery = count($extraParams) > 0 ? "&" . implode("&", $extraParams) : "";
    ?>
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
            $tiempoRestanteTexto = "";
            if (!$estadoActual) {
                if (($producto["estado"] ?? "") === "pausado") {
                    $statusBadge = "Pausado";
                } elseif ($fin !== null && $ahora > $fin) {
                    $statusBadge = "Finalizado";
                } elseif ($inicio !== null && $ahora < $inicio) {
                    $statusBadge = "Inicia pronto";
                } else {
                    $statusBadge = "No disponible";
                }
            } elseif ($fin !== null) {
                $secondsLeft = $fin->getTimestamp() - $ahora->getTimestamp();
                if ($secondsLeft > 0) {
                    if ($secondsLeft < 3600) {
                        $tiempoRestanteTexto = "Menos de 1 hora";
                    } elseif ($secondsLeft <= 86400) {
                        $hoursLeft = (int) ceil($secondsLeft / 3600);
                        $tiempoRestanteTexto = "Faltan " . $hoursLeft . " horas";
                    } else {
                        $daysLeft = (int) ceil($secondsLeft / 86400);
                        $tiempoRestanteTexto = "Faltan " . $daysLeft . " dias";
                    }
                }
            }
        ?>
        <div class="auction-card bg-white dark:bg-slate-900 rounded-3xl overflow-hidden shadow-md border border-slate-100 dark:border-slate-800 flex flex-col">
            <div class="relative h-60 overflow-hidden">
                <?php if ($img !== "") { ?>
                    <img alt="<?php echo htmlspecialchars($producto["nombre"] ?? "Producto"); ?>" class="w-full h-full object-cover" src="<?php echo htmlspecialchars($img); ?>"/>
                <?php } ?>
                <div class="absolute top-4 right-4 bg-white/90 dark:bg-slate-800/90 backdrop-blur-sm px-3 py-1 rounded-full text-xs font-bold text-primary shadow-sm">
                    <div><?php echo htmlspecialchars($producto["categoria"] ?? ""); ?></div>
                    <?php if ($tiempoRestanteTexto !== "") { ?>
                        <div class="text-[10px] font-semibold text-slate-500">
                            <?php echo htmlspecialchars($tiempoRestanteTexto); ?>
                        </div>
                    <?php } ?>
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
                        <span class="text-xs text-slate-400 uppercase font-semibold">Precio inicial</span>
                        <div class="currency-stack text-secondary">
                            <?php foreach ($monedas as $code) { ?>
                                <div class="currency-line">
                                    <?php echo htmlspecialchars($code . " " . formatCurrency((float)($producto['precio'] ?? 0), $code, $rates)); ?>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                    <a class="bg-[#f78da7] text-white px-5 py-2.5 rounded-xl font-bold text-sm hover:bg-[#d66a85] hover:shadow-lg transition-all" href="producto.php?id=<?php echo (int) $producto["id"]; ?><?php echo $extraQuery; ?>">
                        <?php echo $estadoActual ? "Pujar" : "Ver detalles"; ?>
                    </a>
                </div>
            </div>
        </div>
    <?php } ?>
<?php } ?>
</div>
<div class="mt-16 flex justify-center">
<button class="btn-outline-small flex items-center gap-2 px-8 py-3 border-2 border-primary text-primary hover:bg-primary hover:text-white font-bold rounded-full transition-all">
                Load More Items <span class="material-icons">refresh</span>
</button>
</div>
    </main>
    <footer>
        <div class="footer-pink py-5">
            <div class="container text-center">
                <div class="row justify-content-center">
                    <div class="col-lg-6 mb-4">
                        <div class="mb-4">
                            <img src="../Imagenes/logos_blancos.png" alt="Logos" height="80">
                        </div>
                        <p class="px-lg-5 mb-4" style="font-size: 0.95rem; line-height: 1.6;">
                            Pasitos de Luz es una asociación civil en Bahía de Banderas. Es una organización registrada sin fines de lucro fundada por madres de niños discapacitados para satisfacer sus necesidades terapéuticas, psicológicas, nutricionales, educativas y básicas.
                        </p>
                        <p class="small mb-1">Boulevard Federación | Nayarit, México | C.P. 63737</p>
                        <p class="small">Tel: (+52) 322 135 6302 | info@pasitosdeluz.org</p>
                    </div>

                    <div class="col-lg-4 d-flex flex-column align-items-center justify-content-center border-start border-light border-opacity-25">
                        <h4 class="mb-3 fw-light italic"><i class="bi bi-book me-2"></i> Boletín Informativo</h4>
                        <button class="btn btn-subscribe shadow-sm mb-3">SUBSCRIBETE</button>
                        <a href="#" class="text-white text-decoration-none small opacity-75">AVISO DE PRIVACIDAD</a>
                    </div>
                </div>

                <div class="mt-4 fs-4">
                    <i class="bi bi-telephone mx-2"></i>
                    <i class="bi bi-whatsapp mx-2"></i>
                    <i class="bi bi-geo-alt mx-2"></i>
                    <i class="bi bi-facebook mx-2"></i>
                    <i class="bi bi-instagram mx-2"></i>
                    <i class="bi bi-youtube mx-2"></i>
                </div>
            </div>
        </div>

        <div class="bg-white py-5 border-top">
            <div class="container">
                <div class="row text-center footer-links g-4">
                    <div class="col-6 col-md-3">
                        <h6 class="fw-bold mb-3">Sobre Nosotros</h6>
                        <ul class="list-unstyled small">
                            <li class="mb-2"><a href="#">Historia Pasitos de Luz</a></li>
                            <li><a href="#">Casa Connor</a></li>
                        </ul>
                    </div>
                    <div class="col-6 col-md-3">
                        <h6 class="fw-bold mb-3">Dirección y Junta Administrativa</h6>
                        <ul class="list-unstyled small">
                            <li class="mb-2"><a href="#">Dona por Amor</a></li>
                            <li><a href="#">Se Voluntario</a></li>
                        </ul>
                    </div>
                    <div class="col-6 col-md-3">
                        <h6 class="fw-bold mb-3">Dona de Nuestra Lista de Necesidades</h6>
                        <ul class="list-unstyled small">
                            <li class="mb-2"><a href="#">Finanzas</a></li>
                            <li><a href="#">Campañas</a></li>
                        </ul>
                    </div>
                    <div class="col-6 col-md-3">
                        <h6 class="fw-bold mb-3">Eventos</h6>
                        <ul class="list-unstyled small">
                            <li class="mb-2"><a href="#">Noticias</a></li>
                            <li><a href="#">Preguntas Frecuentes</a></li>
                        </ul>
                    </div>
                </div>
                <div class="text-center small mt-4">
                    <a href="../php/login.php" class="text-decoration-none">Administracion: agregar productos</a>
                </div>
            </div>
        </div>
    </footer>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        var toggler = document.querySelector(".navbar-toggler");
        var collapse = document.getElementById("navbarNav");
        if (!toggler || !collapse) {
            return;
        }

        toggler.addEventListener("click", function (event) {
            event.preventDefault();
            collapse.classList.toggle("show");
            toggler.setAttribute("aria-expanded", collapse.classList.contains("show") ? "true" : "false");
        });
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
