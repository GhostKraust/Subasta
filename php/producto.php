<?php
require_once __DIR__ . "/lib/security.php";
start_secure_session();
require_once __DIR__ . "/../config/db.php";

$id = (int) ($_GET["id"] ?? 0);
$categoriaFiltro = (int) ($_GET["categoria"] ?? 0);
$ordenFiltro = trim($_GET["orden"] ?? "");
$busqueda = trim($_GET["q"] ?? "");
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

$hasExpiracion = false;
$checkExp = $mysqli->query("SHOW COLUMNS FROM productos LIKE 'fecha_expiracion'");
if ($checkExp && $checkExp->num_rows > 0) {
    $hasExpiracion = true;
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
$selectExpiracion = $hasExpiracion ? ", p.fecha_expiracion" : "";
$stmt = $mysqli->prepare("SELECT p.id, p.nombre, p.descripcion, p.imagen_url, p.estado, p.$precioColumn AS precio, c.nombre AS categoria$selectIncremento$selectInicio$selectFin$selectExpiracion FROM productos p LEFT JOIN categorias c ON p.categoria_id = c.id WHERE p.id = ? LIMIT 1");
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

$countdown = null;
$interval = null;

if ($inicio !== null && $ahora < $inicio) {
    $interval = $ahora->diff($inicio);
    $countdown = ["title" => "La subasta inicia en"];
} elseif ($estadoActual && $fin !== null) {
    $interval = $ahora->diff($fin);
    $countdown = ["title" => "La subasta termina en"];
}

if ($interval) {
    $countdown["values"] = [];
    if ($interval->days > 0) $countdown["values"][] = ["value" => $interval->days, "label" => "días"];
    if ($interval->h > 0 || $interval->days > 0) $countdown["values"][] = ["value" => $interval->h, "label" => "hrs"];
    $countdown["values"][] = ["value" => $interval->i, "label" => "min"];
    $countdown["values"][] = ["value" => $interval->s, "label" => "seg"];
}

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

$volver = "subasta.php";
$volverParams = [];
if ($categoriaFiltro > 0) {
    $volverParams[] = "categoria=" . $categoriaFiltro;
}
if ($ordenFiltro !== "") {
    $volverParams[] = "orden=" . urlencode($ordenFiltro);
}
if ($busqueda !== "") {
    $volverParams[] = "q=" . urlencode($busqueda);
}
if (count($volverParams) > 0) {
    $volver .= "?" . implode("&", $volverParams);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title><?php echo htmlspecialchars($producto["nombre"] ?? "Producto"); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet" />
    <link href="../css/subasta.css" rel="stylesheet" />
    <style>
        .bg-primary { background-color: #FF91AF !important; }
        .text-primary { color: #FF91AF !important; }
        .bg-secondary { background-color: #00B4FF !important; }
        .text-secondary { color: #00B4FF !important; }
        .btn { background: linear-gradient(135deg, #FF91AF, #FFB3C6) !important; color: white !important; }
        .btn:hover { background: linear-gradient(135deg, #E87199, #FF9DB8) !important; }
    </style>
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

        // Cronómetro en tiempo real
        (function() {
            <?php
                $endTime = null;
                if ($inicio !== null && $ahora < $inicio) {
                    $endTime = $inicio->getTimestamp() * 1000;
                } elseif ($fin !== null) {
                    $endTime = $fin->getTimestamp() * 1000;
                }
            ?>
            
            const endTime = <?php echo $endTime ? $endTime : 'null'; ?>;
            if (!endTime) return;

            function updateCountdown() {
                const now = Date.now();
                const remaining = endTime - now;

                if (remaining <= 0) {
                    document.querySelectorAll('[data-countdown]').forEach(el => {
                        el.innerHTML = '<div class="text-center text-slate-500">La subasta ha finalizado</div>';
                    });
                    return;
                }

                const days = Math.floor(remaining / (1000 * 60 * 60 * 24));
                const hours = Math.floor((remaining % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((remaining % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((remaining % (1000 * 60)) / 1000);

                const countdownItems = [];
                <?php if ($inicio !== null && $ahora < $inicio) { ?>
                    if (days > 0) countdownItems.push({ value: days, label: 'días' });
                    if (hours > 0 || days > 0) countdownItems.push({ value: hours, label: 'hrs' });
                <?php } else { ?>
                    if (days > 0) countdownItems.push({ value: days, label: 'días' });
                    if (hours > 0 || days > 0) countdownItems.push({ value: hours, label: 'hrs' });
                <?php } ?>
                countdownItems.push({ value: minutes, label: 'min' });
                countdownItems.push({ value: seconds, label: 'seg' });

                let html = '<div class="flex justify-center items-baseline gap-4 mt-2">';
                countdownItems.forEach(item => {
                    html += `<div><div class="text-4xl font-bold text-primary">${item.value}</div><div class="text-xs text-slate-500">${item.label}</div></div>`;
                });
                html += '</div>';

                const countdownEl = document.querySelector('[data-countdown]');
                if (countdownEl) {
                    const titleEl = countdownEl.querySelector('h3');
                    countdownEl.innerHTML = (titleEl ? `<h3 class="text-sm uppercase font-semibold text-slate-400 tracking-wider">${titleEl.textContent}</h3>` : '') + html;
                }
            }

            updateCountdown();
            setInterval(updateCountdown, 1000);
        })();

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
            <?php if (count($imagenes) > 1) { ?>
                <div id="carousel-producto" class="carousel slide carousel-dark h-96" data-bs-ride="carousel">
                    <div class="carousel-inner h-full">
                        <?php foreach ($imagenes as $index => $imagen) { ?>
                            <div class="carousel-item h-full <?php echo $index === 0 ? 'active' : ''; ?>">
                                <img src="<?php echo htmlspecialchars($imagen); ?>" class="d-block w-100 h-full object-cover" alt="<?php echo htmlspecialchars($producto["nombre"] ?? "Producto"); ?>">
                            </div>
                        <?php } ?>
                    </div>
                    <button class="carousel-control-prev" type="button" data-bs-target="#carousel-producto" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Previous</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#carousel-producto" data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Next</span>
                    </button>
                </div>
            <?php } elseif (!empty($imagenes[0])) { ?>
                <img class="w-full h-96 object-cover" src="<?php echo htmlspecialchars($imagenes[0]); ?>" alt="<?php echo htmlspecialchars($producto["nombre"] ?? "Producto"); ?>" />
            <?php } ?>
            <div class="p-6 grid gap-4">
                <p class="text-slate-500 leading-relaxed"><?php echo htmlspecialchars($producto["descripcion"] ?? ""); ?></p>
                <div class="flex flex-wrap gap-6 justify-center">
                    <div class="text-center">
                        <div class="text-sm uppercase text-slate-400 font-semibold">Precio inicial</div>
                        <div class="text-3xl font-bold text-slate-700 dark:text-slate-200">MXN $<?php echo number_format((float)($producto['precio'] ?? 0), 2); ?></div>
                    </div>
                    <div class="text-center">
                        <div class="text-sm uppercase text-slate-400 font-semibold">Puja actual</div>
                        <div class="text-3xl font-bold text-secondary">MXN $<?php echo number_format($precioActual, 2); ?></div>
                    </div>
                    <?php if ($incremento > 0) { ?>
                        <div class="text-center">
                            <div class="text-sm uppercase text-slate-400 font-semibold">Minimo</div>
                            <div class="text-3xl font-bold text-slate-700 dark:text-slate-200">$<?php echo number_format($minimo, 2); ?></div>
                        </div>
                    <?php } ?>
                    <?php if (!empty($producto["fecha_expiracion"])) { ?>
                        <div class="text-center">
                            <div class="text-sm uppercase text-slate-400 font-semibold">Válido hasta</div>
                            <div class="text-3xl font-bold text-slate-700 dark:text-slate-200"><?php echo date("d/m/Y", strtotime($producto["fecha_expiracion"])); ?></div>
                        </div>
                    <?php } ?>
                </div>
                <?php if (!$estadoActual) { ?>
                    <div class="bg-slate-100 dark:bg-slate-800/60 text-slate-500 rounded-2xl px-4 py-3 text-sm">
                        <?php if (($producto["estado"] ?? "") === "pausado") { ?>
                            Esta subasta esta pausada.
                        <?php } elseif (($producto["estado"] ?? "") === "finalizado" || ($fin !== null && $ahora > $fin)) { ?>
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
            <?php if ($countdown !== null && !empty($countdown["values"])) { ?>
                <div class="text-center bg-slate-50 dark:bg-slate-800/50 p-4 rounded-2xl" data-countdown>
                    <h3 class="text-sm uppercase font-semibold text-slate-400 tracking-wider"><?php echo htmlspecialchars($countdown['title']); ?></h3>
                    <div class="flex justify-center items-baseline gap-4 mt-2">
                        <?php foreach($countdown['values'] as $item) { ?>
                            <div>
                                <div class="text-4xl font-bold text-primary"><?php echo htmlspecialchars($item['value']); ?></div>
                                <div class="text-xs text-slate-500"><?php echo htmlspecialchars($item['label']); ?></div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>

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
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="producto_id" value="<?php echo (int) $producto["id"]; ?>" />
                    <input type="hidden" name="categoria" value="<?php echo (int) $categoriaFiltro; ?>" />
                    <input type="hidden" name="orden" value="<?php echo htmlspecialchars($ordenFiltro); ?>" />
                    <input type="hidden" name="q" value="<?php echo htmlspecialchars($busqueda); ?>" />
                    <div id="anonimo-field" class="grid gap-2">
                        <input id="nombre-usuario" class="bg-slate-50 dark:bg-slate-800 border-none rounded-lg text-base px-4 py-3 focus:ring-primary" name="nombre_usuario" required placeholder="Nombre" type="text" />
                    </div>
                    <label class="flex items-center gap-3 text-base text-slate-500">
                        <input id="anonimo-toggle" type="checkbox" name="anonimo" value="1" class="w-5 h-5 cursor-pointer" />
                        Puja anonima
                    </label>
                    <input class="bg-slate-50 dark:bg-slate-800 border-none rounded-lg text-base px-4 py-3 focus:ring-primary" name="correo_usuario" required placeholder="Correo" type="email" />
                    <input class="bg-slate-50 dark:bg-slate-800 border-none rounded-lg text-base px-4 py-3 focus:ring-primary" name="telefono_usuario" required placeholder="Telefono" type="tel" />
                    <div class="flex items-center gap-2">
                        <input id="monto-puja-input" class="bg-slate-50 dark:bg-slate-800 border-none rounded-lg text-base px-4 py-3 focus:ring-primary w-full" name="monto_puja" required min="<?php echo number_format($minimo, 2, '.', ''); ?>" step="0.01" placeholder="Monto (mín. $<?php echo number_format($minimo, 0); ?>)" type="number" />
                        <button type="button" id="use-min-bid-btn" data-minimo="<?php echo number_format($minimo, 2, '.', ''); ?>" class="flex-shrink-0 bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 text-sm font-bold px-4 py-3 rounded-lg hover:bg-slate-300 dark:hover:bg-slate-600">
                            Mínimo
                        </button>
                    </div>
                    <button class="bg-primary text-white px-5 py-3 rounded-xl font-bold text-base hover:shadow-lg hover:shadow-primary/30 transition-all" type="submit">
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
                                <?php
                                    $esAnonimo = $hasOrigen && ($puja["origen"] ?? "") === "anonimo";
                                    $nombreMostrado = $esAnonimo ? "Anonimo" : htmlspecialchars($puja["nombre_usuario"] ?? "");
                                ?>
                                <span class="truncate max-w-[60%]"><?php echo $nombreMostrado; ?></span>
                                <span class="font-bold text-lg">$<?php echo number_format((float) ($puja["monto_puja"] ?? 0), 2); ?></span>
                            </div>
                            <?php if ($hasOrigen && !empty($puja["origen"]) && !$esAnonimo) { ?>
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
    <script>
        (function () {
            var toggle = document.getElementById("anonimo-toggle");
            var nombre = document.getElementById("nombre-usuario");
            var field = document.getElementById("anonimo-field");
            if (!toggle || !nombre || !field) {
                return;
            }

            function syncAnonimo() {
                // El campo de nombre ahora es siempre visible y requerido.
                // El checkbox solo actua como una bandera para el backend.
            }

            toggle.addEventListener("change", syncAnonimo);
            syncAnonimo();

            var minBidBtn = document.getElementById("use-min-bid-btn");
            var montoInput = document.getElementById("monto-puja-input");

            if (minBidBtn && montoInput) {
                minBidBtn.addEventListener("click", function() {
                    var minimo = this.dataset.minimo;
                    if (minimo) {
                        montoInput.value = minimo;
                        montoInput.focus();
                    }
                });
            }

            // Autoplay de carruseles
            var carousels = document.querySelectorAll('.carousel');
            carousels.forEach(function(carousel) {
                new bootstrap.Carousel(carousel, {
                    interval: 5000,
                    wrap: true,
                    keyboard: true
                });
            });
        })();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
