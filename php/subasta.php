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

$hasExpiracion = false;
$checkExp = $mysqli->query("SHOW COLUMNS FROM productos LIKE 'fecha_expiracion'");
if ($checkExp && $checkExp->num_rows > 0) {
    $hasExpiracion = true;
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
$selectExpiracion = $hasExpiracion ? ", p.fecha_expiracion" : "";
$queryProductos = "SELECT p.id, p.nombre, p.descripcion, p.imagen_url, p.estado, p.$precioColumn AS precio, c.nombre AS categoria, pu.max_puja$selectIncremento$selectInicio$selectFin$selectExpiracion FROM productos p LEFT JOIN categorias c ON p.categoria_id = c.id LEFT JOIN (SELECT producto_id, MAX(monto_puja) AS max_puja FROM pujas GROUP BY producto_id) pu ON p.id = pu.producto_id WHERE p.estado IN ('activo', 'pausado')";
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

$footerSections = [
    [
        "title" => "About Us",
        "title_url" => "https://pasitosdeluz.org/about-us/",
        "links" => [
            ["label" => "Pasitos de Luz History", "url" => "https://pasitosdeluz.org/about-us/pasitos-de-luz-history/"],
            ["label" => "Casa Connor", "url" => "https://pasitosdeluz.org/about-us/casa-connor/"]
        ]
    ],
    [
        "title" => "Board of Directors",
        "title_url" => "https://pasitosdeluz.org/about-us/board-of-directors/",
        "links" => [
            ["label" => "Love Pasitos Monthly", "url" => "https://pasitosdeluz.org/donate-1/donate1-2/"],
            ["label" => "Volunteer", "url" => "https://pasitosdeluz.org/donate-1/volunteer/"]
        ]
    ],
    [
        "title" => "List of Necessities",
        "title_url" => "https://pasitosdeluz.org/other-ways/list-of-necessities/",
        "links" => [
            ["label" => "Financials", "url" => "https://pasitosdeluz.org/donate/financials/"],
            ["label" => "Campaigns", "url" => "https://pasitosdeluz.org/donate-1/"]
        ]
    ],
    [
        "title" => "Events",
        "title_url" => "https://pasitosdeluz.org/events/",
        "links" => [
            ["label" => "News", "url" => "https://pasitosdeluz.org/news/"],
            ["label" => "Frequently Asked Questions", "url" => "https://pasitosdeluz.org/frequently-asked-questions-2/"]
        ]
    ]
];

?>
<!DOCTYPE html>
<html lang="es"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Subasta.pasitosdelus.org</title>
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
    <style>
        @media (min-width: 992px) {
            .navbar .nav-item.dropdown:hover > .dropdown-menu {
                display: block;
                margin-top: 0;
            }
        }

        .navbar .dropdown-menu {
            border: 0;
            border-radius: 1rem;
            box-shadow: 0 14px 30px rgba(0, 0, 0, 0.12);
            padding: 0.65rem 0;
            min-width: 230px;
        }

        .navbar .dropdown-item {
            font-weight: 600;
            padding: 0.6rem 1rem;
        }

        .navbar .dropdown-item:hover {
            background: #fbe1e9;
            color: #111827;
        }

        .pasitos-footer {
            background: #f78da7;
            color: #ffffff;
        }

        .footer-hero {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            gap: 1.56rem;
        }

        .footer-hero .footer-logo img {
            max-height: 150px;
        }

        .footer-cta-btn {
            border: 2px solid #ffffff;
            color: #ffffff;
            background: transparent;
            padding: 10px 22px;
            font-weight: 600;
            font-size: 1.2rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            transition: all 0.3s ease;
        }

        .footer-cta-btn:hover {
            background-color: #ffffff;
            color: #00B4FF;
            border-color: #ffffff;
        }

        .footer-links a {
            color: #ffffff;
            white-space-collapse: preserve-breaks;
            font-size: small;
            font-weight: 1000;
            letter-spacing: 0.00em;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .footer-links a:hover,
        .footer-heading:hover {
            color: #00B4FF;
        }

        .footer-heading {
            display: inline-block;
            font-weight: 700;
            font-size: 1.05rem;
            letter-spacing: 0.07em;
            margin-bottom: 0.75rem;
            color: #ffffff;
            text-transform: uppercase;
        }

        .footer-links li + li {
            margin-top: 0.35rem;
        }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark text-slate-800 dark:text-slate-200">
<header>
    <div class="top-bar py-2 text-white px-4 d-flex justify-content-between align-items-center">
        <div class="fw-bold">
            <i class="bi bi-telephone-fill me-2" ></i><a href="tel:+52%203221356302">Contáctanos: (+52 322 135 6302)</a> 
        </div>
        <div class="d-flex gap-2 fs-5">
            <a href= "tel:+523221037938" class="top-icon"><i class="bi bi-telephone"></i></a>
            <a href="https://api.whatsapp.com/send/?phone=523221356302&text&type=phone_number&app_absent=0" class="top-icon"><i class="bi bi-whatsapp"></i></a>
            <a href="https://www.google.com/maps/place/Pasitos+de+Luz+-+Casa+Connor/@20.7124787,-105.2493829,15z/data=!4m5!3m4!1s0x0:0xe4e780d17677252d!8m2!3d20.7124787!4d-105.2493829?shorturl=1" class="top-icon"><i class="bi bi-geo-alt-fill"></i></a>
            <a href="https://www.facebook.com/pasitosdeluz2" class="top-icon"><i class="bi bi-facebook"></i></a>
            <a href="https://www.instagram.com/pasitosdeluz/?hl=es" class="top-icon"><i class="bi bi-instagram"></i></a>
            <a href="https://www.youtube.com/@PasitosdeLuz" class="top-icon"><i class="bi bi-youtube"></i></a>
        </div>
    </div>

    <nav class="navbar navbar-expand-lg navbar-light bg-white py-3 shadow-sm">
        <div class="container-fluid px-4">
            <a class="navbar-brand d-flex align-items-center" href="https://pasitosdeluz.org/">
                <img src="../Imagenes/logo_pasitos-removebg-preview.png" alt="Pasitos de Luz" class="me-2 logo-pasitos">
                <img src="../Imagenes/connor29.png" alt="Casa Connor" height="55">
            </a>

            <div class="navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto fw-semibold" style="font-size: 0.95rem;">
                    <li class="nav-item"><a class="nav-link active" href="https://pasitosdeluz.org/">Home</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="https://pasitosdeluz.org/about-us/" role="button" data-bs-toggle="dropdown" aria-expanded="false">About Us</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="https://pasitosdeluz.org/about-us/our-mission-vision-and-values/">Our Mission, Vision, Values</a></li>
                            <li><a class="dropdown-item" href="https://pasitosdeluz.org/about-us/status-recognition/">Status/Recognition</a></li>
                            <li><a class="dropdown-item" href="https://pasitosdeluz.org/about-us/pasitos-de-luz-history/">Pasitos de Luz History</a></li>
                            <li><a class="dropdown-item" href="https://pasitosdeluz.org/about-us/casa-connor/">Casa Connor</a></li>
                            <li><a class="dropdown-item" href="https://pasitosdeluz.org/about-us/board-of-directors/">Board of Directors</a></li>
                            <li><a class="dropdown-item" href="https://pasitosdeluz.org/about-us/board-of-directors/">Our Milestone Timeline</a></li>
                            <li><a class="dropdown-item" href="https://pasitosdeluz.org/about-us/quick-facts/">Quick Facts</a></li>
                            <li><a class="dropdown-item" href="https://pasitosdeluz.org/frequently-asked-questions-2/">Frequently Asked Questions</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="https://pasitosdeluz.org/our-services/" role="button" data-bs-toggle="dropdown" aria-expanded="false">Our Services</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="https://pasitosdeluz.org/our-services/therapy-rehabilitation/">Therapy/rehabilitation</a></li>
                            <li><a class="dropdown-item" href="https://pasitosdeluz.org/our-services/educational-programs/">Educational programs</a></li>
                            <li><a class="dropdown-item" href="https://pasitosdeluz.org/our-services/nutrition-and-wellbeing/">Nutrition and wellbeing</a></li>
                            <li><a class="dropdown-item" href="https://pasitosdeluz.org/our-services/recreation/">Recreation </a></li>
                            <li><a class="dropdown-item" href="https://pasitosdeluz.org/our-impact/">Our impact</a></li>
                        </ul>
                    </li>
                    <li class="nav-item"><a class="nav-link" href="https://pasitosdeluz.org/meet-our-kids/">Meet Our Kids</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="https://pasitosdeluz.org/donate/" role="button" data-bs-toggle="dropdown" aria-expanded="false">Donate</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="https://pasitosdeluz.org/donate/legacy-and-planned-giving/">Legacy and planned giving</a></li>
                            <li><a class="dropdown-item" href="https://pasitosdeluz.org/donate/financials/">Finacials</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="https://pasitosdeluz.org/donate-1/" role="button" data-bs-toggle="dropdown" aria-expanded="false">Campaigns</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="https://pasitosdeluz.org/donate-1/donate1-2/">Love pasitos monthly</a></li>
                            <li><a class="dropdown-item" href="https://pasitosdeluz.org/give-a-day-of-hope-for-2025/">Give a Day of Hope</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="https://pasitosdeluz.org/other-ways/" role="button" data-bs-toggle="dropdown" aria-expanded="false">Other Ways to Help</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="https://pasitosdeluz.org/donate-1/volunteer/">Volunteer</a></li>
                            <li><a class="dropdown-item" href="https://pasitosdeluz.org/other-ways/list-of-necessities/">List of necessities</a></li></a></li>
                        </ul>
                    </li>
                    <li class="nav-item"><a class="nav-link" href="https://pasitosdeluz.org/events/">Events</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="https://pasitosdeluz.org/contact-us/" role="button" data-bs-toggle="dropdown" aria-expanded="false">Contact us</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="https://pasitosdeluz.org/our-tours/">Tours</a></li>
                            <li><a class="dropdown-item" href="https://pasitosdeluz.org/news/">News</a></li>
                        </ul>
                    </li>
                    <li class="nav-item d-flex align-items-center">
                        <a class="nav-link nav-flag" href="https://pasitosdeluz.org/es/inicio/" aria-label="Mexico">
                            <img src="https://flagcdn.com/w20/mx.png" alt="Mexico">
                        </a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <button class="btn btn-donar px-4 py-2 fw-bold shadow-sm" >
                         <a href="https://pasitosdeluz.org/donate/">DONAR</a> <i class="bi bi-heart-fill ms-1"></i>
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
            $imagenes = json_decode($img, true);
            if (!is_array($imagenes)) {
                $imagenes = [$img];
            }
            $imagenes = array_filter(array_map(function($path) {
                $path = trim($path);
                if ($path === "") return null;
                return ($path[0] !== "/" && !preg_match("~^https?://~", $path)) ? "../" . $path : $path;
            }, $imagenes));
            $imagenes = array_values($imagenes);
            
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
                <?php if (count($imagenes) > 1) { ?>
                    <div id="carousel-<?php echo $producto['id']; ?>" class="carousel slide carousel-dark h-full" data-bs-ride="carousel">
                        <div class="carousel-inner h-full">
                            <?php foreach ($imagenes as $index => $imagen) { ?>
                                <div class="carousel-item h-full <?php echo $index === 0 ? 'active' : ''; ?>">
                                    <img src="<?php echo htmlspecialchars($imagen); ?>" class="d-block w-100 h-full object-cover" alt="<?php echo htmlspecialchars($producto["nombre"] ?? "Producto"); ?>">
                                </div>
                            <?php } ?>
                        </div>
                        <button class="carousel-control-prev" type="button" data-bs-target="#carousel-<?php echo $producto['id']; ?>" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Previous</span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#carousel-<?php echo $producto['id']; ?>" data-bs-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Next</span>
                        </button>
                    </div>
                <?php } elseif (!empty($imagenes[0])) { ?>
                    <img alt="<?php echo htmlspecialchars($producto["nombre"] ?? "Producto"); ?>" class="w-full h-full object-cover" src="<?php echo htmlspecialchars($imagenes[0]); ?>"/>
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
                        <?php if (!empty($producto["fecha_expiracion"])) { ?>
                            <div class="text-[11px] text-slate-500 font-medium mt-1">
                                Válido hasta: <?php echo date("d/m/Y", strtotime($producto["fecha_expiracion"])); ?>
                            </div>
                        <?php } ?>
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
    <footer class="pasitos-footer">
        <div class="py-5">
            <div class="container">
                <!-- Header con logos y tagline -->
                <div class="footer-hero mb-5">
                    <div class="footer-logo text-center text-md-start">
                        <img src="../Imagenes/footer1.png" alt="Logos">
                    </div>
                    <div class="text-center">
                        <h2 class="fw-bold" style="font-size: 1.8rem; font-style: italic; color: #ffffff;">
                            "Working with love"
                        </h2>
                    </div>
                    <div class="text-center text-md-end">
                        <a class="footer-cta-btn d-inline-block text-decoration-none" href="https://pasitosdeluz.us7.list-manage.com/subscribe?u=eef627f1c0a1639264454b16b&id=2ac7ab04c2" target="_blank" rel="noopener">
                            Join Our Newsletter
                        </a>
                    </div>
                </div>

                <!-- Descripción -->
                <div class="row justify-content-center mb-4">
                    <div class="col-lg-10 text-center">
                        <p class="mb-4" style="font-size: 1rem; line-height: 1.7; font-weight: 500; color: #ffffff;">
                            Pasitos de Luz is a civil association in Banderas Bay. It is a registered non- profit organization founded by mothers of disabled children to meet their therapeutic, psychological, nutritional, educational and basic needs.
                        </p>
                    </div>
                </div>

                <!-- Contacto -->
                <div class="row justify-content-center text-center mb-4">
                    <div class="col-lg-8">
                        <p class="small mb-1" style="color: #ffffff;">Boulevard Federación | Nayarit, México | C.P. 63737</p>
                        <p class="small" style="color: #ffffff;">Tel: (+52) 322 135 6302 / info@pasitosdeluz.org</p>
                    </div>
                </div>

                <!-- Redes Sociales -->
                <div class="row justify-content-center mb-4">
                    <div class="col-auto">
                        <div class="d-flex gap-3 justify-content-center" style="font-size: 1.8rem;">
                            <a href="tel:+523221037938" style="color: #f78da7; background: #ffffff; width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: all 0.3s ease;" onmouseover="this.style.color='#00B4FF'; this.style.transform='translateY(-8px)';" onmouseout="this.style.color='#f78da7'; this.style.transform='translateY(0)';"><i class="bi bi-telephone"></i></a>
                            <a href="https://api.whatsapp.com/send/?phone=523221356302&text&type=phone_number&app_absent=0" style="color: #f78da7; background: #ffffff; width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: all 0.3s ease;" onmouseover="this.style.color='#00B4FF'; this.style.transform='translateY(-8px)';" onmouseout="this.style.color='#f78da7'; this.style.transform='translateY(0)';"><i class="bi bi-whatsapp"></i></a>
                            <a href="https://www.google.com/maps/place/Pasitos+de+Luz+-+Casa+Connor/@20.7124787,-105.2493829,15z/data=!4m5!3m4!1s0x0:0xe4e780d17677252d!8m2!3d20.7124787!4d-105.2493829?shorturl=1" style="color: #f78da7; background: #ffffff; width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: all 0.3s ease;" onmouseover="this.style.color='#00B4FF'; this.style.transform='translateY(-8px)';" onmouseout="this.style.color='#f78da7'; this.style.transform='translateY(0)';"><i class="bi bi-geo-alt"></i></a>
                            <a href="https://www.facebook.com/pasitosdeluz2" style="color: #f78da7; background: #ffffff; width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: all 0.3s ease;" onmouseover="this.style.color='#00B4FF'; this.style.transform='translateY(-8px)';" onmouseout="this.style.color='#f78da7'; this.style.transform='translateY(0)';"><i class="bi bi-facebook"></i></a>
                            <a href="https://www.instagram.com/pasitosdeluz/?hl=es" style="color: #f78da7; background: #ffffff; width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: all 0.3s ease;" onmouseover="this.style.color='#00B4FF'; this.style.transform='translateY(-8px)';" onmouseout="this.style.color='#f78da7'; this.style.transform='translateY(0)';"><i class="bi bi-instagram"></i></a>
                            <a href="https://www.youtube.com/@PasitosdeLuz" style="color: #f78da7; background: #ffffff; width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: all 0.3s ease;" onmouseover="this.style.color='#00B4FF'; this.style.transform='translateY(-8px)';" onmouseout="this.style.color='#f78da7'; this.style.transform='translateY(0)';"><i class="bi bi-youtube"></i></a>
                        </div>
                    </div>
                </div>

                <!-- Privacy Link -->
                <div class="row justify-content-center mb-5">
                    <div class="col-auto text-center">
                        <a href="https://pasitosdeluz.org/privacy/" class="text-decoration-none" style="font-weight: 700; letter-spacing: 0.1em; font-size: 1.0rem; color: #ffffff;">PRIVACY POLICY</a>
                    </div>
                </div>

                <!-- Links Section -->
                <div class="row text-center footer-links g-4">
                    <?php foreach ($footerSections as $section) { ?>
                        <div class="col-6 col-md-3">
                            <a class="footer-heading" href="<?php echo htmlspecialchars($section["title_url"]); ?>" target="_blank" rel="noopener">
                                <?php echo htmlspecialchars($section["title"]); ?>
                            </a>
                            <ul class="list-unstyled small">
                                <?php foreach ($section["links"] as $link) { ?>
                                    <li>
                                        <a href="<?php echo htmlspecialchars($link["url"]); ?>" target="_blank" rel="noopener">
                                            <?php echo htmlspecialchars($link["label"]); ?>
                                        </a>
                                    </li>
                                <?php } ?>
                            </ul>
                        </div>
                    <?php } ?>
                </div>
                <div class="text-center small mt-4">
                    <a href="../php/login.php" class="text-decoration-none" style="color: #ffffff; letter-spacing: 0.05em;">Administracion: agregar productos</a>
                </div>
            </div>
        </div>
    </footer>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        var toggler = document.querySelector(".navbar-toggler");
        var collapse = document.getElementById("navbarNav");
        var desktopMediaQuery = window.matchMedia("(min-width: 992px)");
        if (!toggler || !collapse) {
            // Continue running menu helpers even if collapsible toggle is missing.
        } else {
            toggler.addEventListener("click", function (event) {
                event.preventDefault();
                collapse.classList.toggle("show");
                toggler.setAttribute("aria-expanded", collapse.classList.contains("show") ? "true" : "false");
            });
        }

        var dropdownParents = document.querySelectorAll(".navbar .nav-item.dropdown > .nav-link");
        dropdownParents.forEach(function (link) {
            link.addEventListener("click", function (event) {
                var destination = link.getAttribute("href");
                if (!desktopMediaQuery.matches || !destination || destination === "#") {
                    return;
                }

                event.preventDefault();
                window.location.href = destination;
            });
        });

        // Autoplay de carruseles
        var carousels = document.querySelectorAll('.carousel');
        carousels.forEach(function(carousel) {
            var bootstrapCarousel = new bootstrap.Carousel(carousel, {
                interval: 5000,
                wrap: true,
                keyboard: true
            });
        });
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
