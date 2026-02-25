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
$resultAdmins = $mysqli->query("SELECT id, usuario FROM admin ORDER BY id DESC");
if ($resultAdmins) {
    while ($row = $resultAdmins->fetch_assoc()) {
        $admins[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Administracion - Agregar producto</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@400;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link href="../css/style.css" rel="stylesheet" />
</head>
<body class="auth-page">
    <main class="auth admin-layout">
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

        <section class="auth-card admin-products">
            <h2 class="section-title">Productos existentes</h2>
            <div class="table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Imagen</th>
                            <th>Producto</th>
                            <th>Categoria</th>
                            <th>Precio inicial</th>
                            <?php if ($hasIncremento) { ?>
                                <th>Incremento</th>
                            <?php } ?>
                            <th>Estado</th>
                            <th>Ganador</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($productos) === 0) { ?>
                            <tr>
                                <td colspan="<?php echo $hasIncremento ? 8 : 7; ?>">No hay productos registrados.</td>
                            </tr>
                        <?php } else { ?>
                            <?php foreach ($productos as $producto) { ?>
                                <?php
                                    $img = $producto["imagen_url"] ?? "";
                                    if ($img !== "" && $img[0] !== "/" && !preg_match("~^https?://~", $img)) {
                                        $img = "../" . $img;
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <?php if ($img !== "") { ?>
                                            <img class="thumb" src="<?php echo htmlspecialchars($img); ?>" alt="" />
                                        <?php } ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($producto["nombre"] ?? ""); ?></td>
                                    <td><?php echo htmlspecialchars($producto["categoria"] ?? "Sin categoria"); ?></td>
                                    <td>
                                        <div class="currency-stack">
                                            <?php foreach ($monedas as $code) { ?>
                                                <div class="currency-line">
                                                    <?php echo htmlspecialchars($code . " " . formatCurrency((float) $producto["precio"], $code, $rates)); ?>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    </td>
                                    <?php if ($hasIncremento) { ?>
                                        <td>
                                            <div class="currency-stack">
                                                <?php foreach ($monedas as $code) { ?>
                                                    <div class="currency-line">
                                                        <?php echo htmlspecialchars($code . " " . formatCurrency((float) ($producto["incremento_minimo"] ?? 0), $code, $rates)); ?>
                                                    </div>
                                                <?php } ?>
                                            </div>
                                        </td>
                                    <?php } ?>
                                    <td><span class="status-tag"><?php echo htmlspecialchars($producto["estado"] ?? ""); ?></span></td>
                                    <td>
                                        <?php if (($producto["estado"] ?? "") === "finalizado") { ?>
                                            <?php $ganador = $ganadores[(int) $producto["id"]] ?? null; ?>
                                            <?php if ($ganador) { ?>
                                                <div class="winner">
                                                    <div class="winner-name"><?php echo htmlspecialchars($ganador["nombre_usuario"] ?? ""); ?></div>
                                                    <div class="winner-meta"><?php echo htmlspecialchars($ganador["correo_usuario"] ?? ""); ?></div>
                                                    <div class="winner-meta"><?php echo htmlspecialchars($ganador["telefono_usuario"] ?? ""); ?></div>
                                                    <div class="winner-amount">
                                                        <div class="currency-stack">
                                                            <?php foreach ($monedas as $code) { ?>
                                                                <div class="currency-line">
                                                                    <?php echo htmlspecialchars($code . " " . formatCurrency((float) ($ganador["monto_puja"] ?? 0), $code, $rates)); ?>
                                                                </div>
                                                            <?php } ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php } else { ?>
                                                <span class="text-xs text-slate-400">Sin pujas</span>
                                            <?php } ?>
                                        <?php } else { ?>
                                            <span class="text-xs text-slate-400">-</span>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <div class="action-row">
                                            <a class="btn btn-small btn-outline" href="editar_producto.php?id=<?php echo (int) $producto["id"]; ?>">Editar</a>
                                            <form action="eliminar_producto.php" method="post" onsubmit="return confirm('Seguro que quieres eliminar este producto?');">
                                                <input type="hidden" name="id" value="<?php echo (int) $producto["id"]; ?>" />
                                                <button class="btn btn-small" type="submit">Eliminar</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="auth-card admin-staff">
            <h2 class="section-title">Personal administrativo</h2>
            <?php if ($staffStatus === "created") { ?>
                <p class="lead">Personal agregado correctamente.</p>
            <?php } elseif ($staffStatus === "updated") { ?>
                <p class="lead">Personal actualizado correctamente.</p>
            <?php } elseif ($staffStatus === "deleted") { ?>
                <p class="lead">Personal eliminado correctamente.</p>
            <?php } elseif ($staffStatus === "exists") { ?>
                <p class="lead">Ese usuario ya existe. Usa otro nombre.</p>
            <?php } elseif ($staffStatus === "self") { ?>
                <p class="lead">No puedes eliminar tu propio acceso.</p>
            <?php } elseif ($staffStatus === "error") { ?>
                <p class="lead">No se pudo completar la accion. Intenta de nuevo.</p>
            <?php } else { ?>
                <p class="lead">Agrega, edita o elimina usuarios con acceso al panel.</p>
            <?php } ?>
            <form class="auth-form" action="guardar_personal.php" method="post">
                <label class="field">
                    <span>Usuario</span>
                    <input name="usuario" type="text" required placeholder="Ej. admin2" />
                </label>
                <label class="field">
                    <span>Contrasena</span>
                    <input name="contrasena" type="password" required placeholder="********" />
                </label>
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

        <section class="auth-card admin-presencial">
            <h2 class="section-title">Registrar puja presencial</h2>
            <form class="auth-form" action="puja_presencial.php" method="post">
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

        <section class="auth-card admin-new">
            <div class="auth-brand">
                <div class="brand-mark">A</div>
                <div>
                    <div class="brand-name">Administracion</div>
                    <div class="brand-tag">Agregar producto</div>
                </div>
            </div>
            <h1>Nuevo producto</h1>
            <?php if (!empty($_GET["ok"])) { ?>
                <p class="lead">Producto guardado correctamente.</p>
            <?php } elseif (!empty($_GET["deleted"])) { ?>
                <p class="lead">Producto eliminado correctamente.</p>
            <?php } elseif (!empty($_GET["updated"])) { ?>
                <p class="lead">Producto actualizado correctamente.</p>
            <?php } elseif (!empty($_GET["presencial"])) { ?>
                <p class="lead">Puja presencial registrada correctamente.</p>
            <?php } else { ?>
                <p class="lead">Completa los datos para publicar una nueva subasta.</p>
            <?php } ?>
            <form class="auth-form" action="guardar_producto.php" method="post" enctype="multipart/form-data">
                <label class="field">
                    <span>Nombre del producto</span>
                    <input name="nombre" type="text" required placeholder="Ej. Paquete de viaje" />
                </label>
                <label class="field">
                    <span>Descripcion</span>
                    <input name="descripcion" type="text" required placeholder="Breve descripcion" />
                </label>
                <label class="field">
                    <span>Imagen del producto</span>
                    <input name="imagen" type="file" accept="image/*" required />
                    <small class="field-hint">Se subira al servidor y se guardara en la base de datos.</small>
                </label>
                <label class="field">
                    <span>Precio inicial</span>
                    <input name="precio_inicial" type="number" min="0" step="0.01" required placeholder="800" />
                </label>
                <label class="field">
                    <span>Incremento minimo</span>
                    <input name="incremento_minimo" type="number" min="1" step="1" required placeholder="100" />
                    <?php if (!$hasIncremento) { ?>
                        <small class="field-hint">Agrega la columna incremento_minimo en la tabla productos.</small>
                    <?php } ?>
                </label>
                <?php if ($hasInicio) { ?>
                    <label class="field">
                        <span>Fecha y hora de inicio</span>
                        <input name="fecha_inicio" type="datetime-local" required />
                    </label>
                <?php } ?>
                <?php if ($hasFin) { ?>
                    <label class="field">
                        <span>Fecha y hora de fin</span>
                        <input name="fecha_fin" type="datetime-local" required />
                    </label>
                <?php } ?>
                <label class="field">
                    <span>Categoria</span>
                    <select name="categoria_id" required>
                        <option value="" disabled selected>Selecciona una categoria</option>
                        <?php foreach ($categorias as $categoria) { ?>
                            <option value="<?php echo (int) $categoria["id"]; ?>">
                                <?php echo htmlspecialchars($categoria["nombre"]); ?>
                            </option>
                        <?php } ?>
                    </select>
                </label>
                <button class="btn" type="submit">Guardar producto</button>
            </form>
            <div class="switch">
                <a class="link" href="dashboard.php">Ir al dashboard</a>
                <span>·</span>
                <a class="link" href="subasta.php">Volver a subasta</a>
            </div>
        </section>


    </main>
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
</body>
</html>
