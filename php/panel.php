<?php
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/../config/db.php";

$categorias = [];
$result = $mysqli->query("SELECT id, nombre FROM categorias ORDER BY nombre ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $categorias[] = $row;
    }
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

$hasRole = false;
$checkRole = $mysqli->query("SHOW COLUMNS FROM admin LIKE 'rol'");
if ($checkRole && $checkRole->num_rows > 0) {
    $hasRole = true;
}

$hasOrigen = false;
$checkOrigen = $mysqli->query("SHOW COLUMNS FROM pujas LIKE 'origen'");
if ($checkOrigen && $checkOrigen->num_rows > 0) {
    $hasOrigen = true;
}

$monedas = ["MXN", "USD", "CAD"];
$moneda = strtoupper($_GET["moneda"] ?? "MXN");
if (!in_array($moneda, $monedas, true)) {
    $moneda = "MXN";
}

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

$productos = [];
$selectIncremento = $hasIncremento ? ", p.incremento_minimo" : "";
$selectInicio = $hasInicio ? ", p.fecha_inicio" : "";
$selectFin = $hasFin ? ", p.fecha_fin" : "";
$queryProductos = "SELECT p.id, p.nombre, p.imagen_url, p.estado, p.$precioColumn AS precio, c.nombre AS categoria$selectIncremento$selectInicio$selectFin FROM productos p LEFT JOIN categorias c ON p.categoria_id = c.id ORDER BY p.id DESC";
$resultProductos = $mysqli->query($queryProductos);
if ($resultProductos) {
    while ($row = $resultProductos->fetch_assoc()) {
        $productos[] = $row;
    }
}

$maxPujas = [];
$resultMaxPujas = $mysqli->query("SELECT producto_id, MAX(monto_puja) AS max_puja FROM pujas GROUP BY producto_id");
if ($resultMaxPujas) {
    while ($row = $resultMaxPujas->fetch_assoc()) {
        $maxPujas[(int) $row["producto_id"]] = (float) $row["max_puja"];
    }
}

$nowTimestamp = time();

$ganadores = [];
if (count($productos) > 0) {
    $ids = [];
    foreach ($productos as $producto) {
        if (($producto["estado"] ?? "") === "finalizado") {
            $ids[] = (int) $producto["id"];
        }
    }
    if (count($ids) > 0) {
        $idList = implode(",", $ids);
        $queryGanadores = "SELECT pu.producto_id, pu.nombre_usuario, pu.correo_usuario, pu.telefono_usuario, pu.monto_puja FROM pujas pu INNER JOIN (SELECT producto_id, MAX(monto_puja) AS max_puja FROM pujas WHERE producto_id IN ($idList) GROUP BY producto_id) mx ON pu.producto_id = mx.producto_id AND pu.monto_puja = mx.max_puja";
        $resultGanadores = $mysqli->query($queryGanadores);
        if ($resultGanadores) {
            while ($row = $resultGanadores->fetch_assoc()) {
                $ganadores[(int) $row["producto_id"]] = $row;
            }
        }
    }
}

$admins = [];
$currentAdminId = (int) ($_SESSION["admin_id"] ?? 0);
$staffStatus = trim($_GET["staff"] ?? "");
$pujaStatus = trim($_GET["puja"] ?? "");
$isAdmin = is_admin();
$toast = null;

if (!empty($_GET["presencial"])) {
    $toast = [
        "title" => "Pujas",
        "body" => "Puja presencial registrada correctamente.",
        "variant" => "success"
    ];
} elseif ($staffStatus !== "") {
    if ($staffStatus === "created") {
        $toast = [
            "title" => "Personal",
            "body" => "Personal agregado correctamente.",
            "variant" => "success"
        ];
    } elseif ($staffStatus === "updated") {
        $toast = [
            "title" => "Personal",
            "body" => "Personal actualizado correctamente.",
            "variant" => "success"
        ];
    } elseif ($staffStatus === "deleted") {
        $toast = [
            "title" => "Personal",
            "body" => "Personal eliminado correctamente.",
            "variant" => "warning"
        ];
    } elseif ($staffStatus === "exists") {
        $toast = [
            "title" => "Personal",
            "body" => "Ese usuario ya existe. Usa otro nombre.",
            "variant" => "warning"
        ];
    } elseif ($staffStatus === "self") {
        $toast = [
            "title" => "Personal",
            "body" => "No puedes eliminar tu propio acceso.",
            "variant" => "warning"
        ];
    } else {
        $toast = [
            "title" => "Personal",
            "body" => "No se pudo completar la accion. Intenta de nuevo.",
            "variant" => "danger"
        ];
    }
} elseif ($pujaStatus !== "") {
    if ($pujaStatus === "deleted") {
        $toast = [
            "title" => "Pujas",
            "body" => "Puja eliminada correctamente.",
            "variant" => "warning"
        ];
    } else {
        $toast = [
            "title" => "Pujas",
            "body" => "No se pudo eliminar la puja.",
            "variant" => "danger"
        ];
    }
}
$resultAdmins = $mysqli->query("SELECT id, usuario FROM admin ORDER BY id DESC");
if ($resultAdmins) {
    while ($row = $resultAdmins->fetch_assoc()) {
        $admins[] = $row;
    }
}

$pujas = [];
$selectOrigen = $hasOrigen ? ", pu.origen" : "";
$queryPujas = "SELECT pu.id, pu.producto_id, p.nombre AS producto, pu.nombre_usuario, pu.correo_usuario, pu.telefono_usuario, pu.monto_puja, pu.fecha_puja$selectOrigen " .
    "FROM pujas pu INNER JOIN productos p ON p.id = pu.producto_id " .
    "ORDER BY pu.fecha_puja DESC LIMIT 30";
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
    <title>Administracion - Panel</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@400;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="../css/style.css" rel="stylesheet" />
</head>
<body class="auth-page panel-page">
    <?php if ($toast) { ?>
        <div class="toast-container position-fixed top-0 end-0 p-3">
            <div id="statusToast" class="toast toast-pink" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="4500">
                <div class="toast-header">
                    <strong class="me-auto">Pasistos de luz</strong>
                    <small>Ahora</small>
                    <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body">
                    Tu oferta sea realizado
                </div>
            </div>
        </div>
    <?php } ?>
    <main class="auth admin-layout">
        <div class="panel-actions">
            <a class="btn btn-compact ghost" href="subasta.php">Volver a subasta</a>
            <?php if ($isAdmin) { ?>
                <a class="btn btn-compact ghost" href="productos.php">Productos</a>
                <a class="btn btn-compact ghost" href="graficos.php">Graficas</a>
                <a class="btn btn-compact" href="dashboard.php">Inicio</a>
            <?php } ?>
        </div>
        <form class="currency-bar" method="get">
            <label class="currency-label" for="moneda-select">Moneda</label>
            <select id="moneda-select" name="moneda" onchange="this.form.submit()">
                <?php foreach ($monedas as $code) { ?>
                    <option value="<?php echo htmlspecialchars($code); ?>" <?php echo $code === $moneda ? "selected" : ""; ?>>
                        <?php echo htmlspecialchars($code); ?>
                    </option>
                <?php } ?>
            </select>
        </form>

        <?php if ($isAdmin) { ?>
            <section class="auth-card admin-staff">
                <h2 class="section-title">Personal administrativo</h2>
                <p class="lead">Agrega, edita o elimina usuarios con acceso al panel.</p>
                <form class="auth-form" action="guardar_personal.php" method="post">
                    <?php echo csrf_input(); ?>
                    <label class="field">
                        <span>Usuario</span>
                        <input name="usuario" type="text" required placeholder="Ej. admin2" />
                    </label>
                    <label class="field">
                        <span>Contrasena</span>
                        <input name="contrasena" type="password" required placeholder="********" />
                    </label>
                    <?php if ($hasRole) { ?>
                        <label class="field">
                            <span>Rol</span>
                            <select name="rol" required>
                                <option value="admin">Administrador</option>
                                <option value="operativo" selected>Operativo</option>
                            </select>
                        </label>
                    <?php } ?>
                    <button class="btn" type="submit">Agregar personal</button>
                </form>
                <div class="table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($admins) === 0) { ?>
                                <tr>
                                    <td colspan="2">No hay personal registrado.</td>
                                </tr>
                            <?php } else { ?>
                                <?php foreach ($admins as $admin) { ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($admin["usuario"] ?? ""); ?></td>
                                        <td>
                                            <div class="action-row">
                                                <a class="btn btn-small btn-outline" href="editar_personal.php?id=<?php echo (int) $admin["id"]; ?>">Editar</a>
                                                <form action="eliminar_personal.php" method="post" onsubmit="return confirm('Seguro que quieres eliminar este usuario?');">
                                                    <?php echo csrf_input(); ?>
                                                    <input type="hidden" name="id" value="<?php echo (int) $admin["id"]; ?>" />
                                                    <button class="btn btn-small" type="submit" <?php echo ((int) $admin["id"]) === $currentAdminId ? "disabled" : ""; ?>>Eliminar</button>
                                                </form>
                                                <?php if ((int) $admin["id"] === $currentAdminId) { ?>
                                                    <span class="field-hint">No puedes eliminar tu usuario.</span>
                                                <?php } ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php } ?>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php } ?>

        <section class="auth-card admin-presencial">
            <h2 class="section-title">Registrar puja presencial</h2>
            <form class="auth-form" action="puja_presencial.php" method="post">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="moneda" value="<?php echo htmlspecialchars($moneda); ?>" />
                <label class="field">
                    <span>Buscar producto</span>
                    <input id="presencial-search" type="text" placeholder="Escribe para filtrar" autocomplete="off" />
                    <small class="field-hint" id="presencial-count"></small>
                </label>
                <label class="field">
                    <span>Producto</span>
                    <select id="presencial-select" name="producto_id" required>
                        <option value="" disabled selected>Selecciona un producto</option>
                        <?php foreach ($productos as $producto) { ?>
                            <?php
                                $inicioOk = true;
                                if ($hasInicio) {
                                    $fechaInicio = $producto["fecha_inicio"] ?? "";
                                    if ($fechaInicio !== "") {
                                        $inicioOk = strtotime($fechaInicio) <= $nowTimestamp;
                                    }
                                }
                                $finOk = true;
                                if ($hasFin) {
                                    $fechaFin = $producto["fecha_fin"] ?? "";
                                    if ($fechaFin !== "") {
                                        $finOk = strtotime($fechaFin) >= $nowTimestamp;
                                    }
                                }
                                $productoId = (int) ($producto["id"] ?? 0);
                                $maxPuja = $maxPujas[$productoId] ?? 0.0;
                                $precioBase = (float) ($producto["precio"] ?? 0);
                                if ($maxPuja > $precioBase) {
                                    $precioBase = $maxPuja;
                                }
                                $incremento = $hasIncremento ? (float) ($producto["incremento_minimo"] ?? 0) : 0.0;
                                $minimoPuja = $precioBase + $incremento;
                                $minimoConvertido = convertAmount($minimoPuja, $moneda, $rates);
                            ?>
                            <?php if (($producto["estado"] ?? "") === "activo" && $inicioOk && $finOk) { ?>
                                <?php $nombreProducto = trim($producto["nombre"] ?? ""); ?>
                                <option value="<?php echo $productoId; ?>" data-min="<?php echo htmlspecialchars((string) $minimoConvertido); ?>">
                                    <?php echo htmlspecialchars($nombreProducto); ?>
                                </option>
                            <?php } ?>
                        <?php } ?>
                    </select>
                </label>
                <label class="field">
                    <span>Monto de la puja</span>
                    <input id="presencial-monto" name="monto_puja" type="number" min="1" step="1" required placeholder="900" />
                    <small class="field-hint" id="presencial-min"></small>
                </label>
                <button class="btn" type="submit">Registrar puja presencial</button>
            </form>
        </section>

        <section class="auth-card admin-bids">
            <div class="section-header">
                <h2 class="section-title">Pujas recientes</h2>
                <p class="lead">Administra ofertas invalidas o fuera de lugar.</p>
            </div>
            <form class="auth-form" onsubmit="return false;">
                <label class="field">
                    <span>Buscar por producto o postor</span>
                    <input id="pujas-search" type="text" placeholder="Ej. Rubi Rosa o Juan" autocomplete="off" />
                    <small class="field-hint" id="pujas-count"></small>
                </label>
            </form>
            <div class="table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Postor</th>
                            <th>Contacto</th>
                            <th>Monto</th>
                            <th><?php echo $hasOrigen ? "Origen" : "Fecha"; ?></th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($pujas) === 0) { ?>
                            <tr>
                                <td colspan="6">No hay pujas registradas.</td>
                            </tr>
                        <?php } else { ?>
                            <?php foreach ($pujas as $puja) { ?>
                                <?php
                                    $productoNombre = trim((string) ($puja["producto"] ?? ""));
                                    $postorNombre = trim((string) ($puja["nombre_usuario"] ?? ""));
                                    $correo = trim((string) ($puja["correo_usuario"] ?? ""));
                                    $telefono = trim((string) ($puja["telefono_usuario"] ?? ""));
                                    $searchRaw = $productoNombre . " " . $postorNombre;
                                    $searchBlob = function_exists("mb_strtolower")
                                        ? mb_strtolower($searchRaw, "UTF-8")
                                        : strtolower($searchRaw);
                                ?>
                                <tr data-search="<?php echo htmlspecialchars($searchBlob); ?>">
                                    <td><?php echo htmlspecialchars($productoNombre); ?></td>
                                    <td><?php echo htmlspecialchars($postorNombre); ?></td>
                                    <td>
                                        <div class="text-xs text-slate-500"><?php echo htmlspecialchars($puja["correo_usuario"] ?? ""); ?></div>
                                        <div class="text-xs text-slate-500"><?php echo htmlspecialchars($puja["telefono_usuario"] ?? ""); ?></div>
                                    </td>
                                    <td>$<?php echo number_format((float) ($puja["monto_puja"] ?? 0), 2); ?></td>
                                    <td>
                                        <?php if ($hasOrigen) { ?>
                                            <?php echo htmlspecialchars($puja["origen"] ?? "-"); ?>
                                        <?php } else { ?>
                                            <?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($puja["fecha_puja"] ?? ""))); ?>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <form action="eliminar_puja.php" method="post" onsubmit="return confirm('Seguro que quieres eliminar esta puja?');">
                                            <?php echo csrf_input(); ?>
                                            <input type="hidden" name="id" value="<?php echo (int) ($puja["id"] ?? 0); ?>" />
                                            <input type="hidden" name="moneda" value="<?php echo htmlspecialchars($moneda); ?>" />
                                            <button class="btn btn-small" type="submit">Eliminar</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </section>


    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const toastEl = document.getElementById("statusToast");
        if (toastEl) {
            const toast = new bootstrap.Toast(toastEl);
            toast.show();
        }
    </script>
    <script>
        (function () {
            var searchInput = document.getElementById("presencial-search");
            var select = document.getElementById("presencial-select");
            var countLabel = document.getElementById("presencial-count");
            var minLabel = document.getElementById("presencial-min");
            var montoInput = document.getElementById("presencial-monto");
            var currency = "<?php echo $moneda; ?>";
            if (!searchInput || !select) {
                return;
            }

            var options = Array.prototype.slice.call(select.options).filter(function (opt) {
                return opt.value !== "";
            });

            function updateCount(visibleCount, totalCount) {
                if (!countLabel) {
                    return;
                }
                countLabel.textContent = "Mostrando " + visibleCount + " de " + totalCount + " productos";
            }

            function filterOptions() {
                var query = searchInput.value.trim().toLowerCase();
                var visible = 0;
                options.forEach(function (opt) {
                    var text = opt.textContent.toLowerCase();
                    var match = query === "" || text.indexOf(query) !== -1;
                    opt.hidden = !match;
                    if (match) {
                        visible += 1;
                    }
                });
                updateCount(visible, options.length);
            }

            function updateMinimo() {
                if (!montoInput || !minLabel) {
                    return;
                }
                var selected = select.options[select.selectedIndex];
                if (!selected || !selected.dataset || !selected.dataset.min) {
                    montoInput.min = "1";
                    minLabel.textContent = "";
                    return;
                }
                var minimo = parseInt(selected.dataset.min, 10);
                if (!isFinite(minimo)) {
                    montoInput.min = "1";
                    minLabel.textContent = "";
                    return;
                }
                var prefix = "$";
                if (currency === "USD") {
                    prefix = "US$";
                } else if (currency === "CAD") {
                    prefix = "CA$";
                }
                montoInput.min = String(minimo);
                minLabel.textContent = "Minimo permitido: " + prefix + minimo.toLocaleString();
            }

            searchInput.addEventListener("input", filterOptions);
            select.addEventListener("change", updateMinimo);
            filterOptions();
            updateMinimo();
        })();
    </script>
    <script>
        (function () {
            var searchInput = document.getElementById("pujas-search");
            var countLabel = document.getElementById("pujas-count");
            var table = searchInput ? searchInput.closest("section") : null;
            var rows = table ? table.querySelectorAll("tbody tr[data-search]") : [];
            if (!searchInput || rows.length === 0) {
                return;
            }

            const initialVisibleCount = 5;

            function updateCount(visibleCount, totalCount, isSearching) {
                if (!countLabel) {
                    return;
                }
                if (isSearching) {
                    countLabel.textContent = "Mostrando " + visibleCount + " de " + totalCount + " pujas";
                } else {
                    countLabel.textContent = "Mostrando las " + Math.min(initialVisibleCount, totalCount) + " pujas más recientes de " + totalCount;
                }
            }

            function filterRows() {
                var term = searchInput.value.toLowerCase().trim();
                var visible = 0;
                var isSearching = term !== "";

                rows.forEach(function (row, index) {
                    var haystack = row.getAttribute("data-search") || "";
                    var match = false;

                    if (isSearching) {
                        match = haystack.indexOf(term) !== -1;
                    } else {
                        match = index < initialVisibleCount;
                    }
                    
                    row.style.display = match ? "" : "none";
                    if (match) {
                        visible++;
                    }
                });
                updateCount(visible, rows.length, isSearching);
            }

            filterRows();
            searchInput.addEventListener("input", filterRows);
        })();
    </script>
</body>
</html>
