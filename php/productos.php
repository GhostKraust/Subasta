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

$mostrarFinalizados = !empty($_GET["finalizados"]);

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
$queryProductos = "SELECT p.id, p.nombre, p.imagen_url, p.estado, p.$precioColumn AS precio, c.nombre AS categoria$selectIncremento$selectInicio$selectFin " .
    "FROM productos p LEFT JOIN categorias c ON p.categoria_id = c.id";
if (!$mostrarFinalizados) {
    $queryProductos .= " WHERE p.estado <> 'finalizado'";
}
$queryProductos .= " ORDER BY p.id DESC";
$resultProductos = $mysqli->query($queryProductos);
if ($resultProductos) {
    while ($row = $resultProductos->fetch_assoc()) {
        $productos[] = $row;
    }
}

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

$estadoStatus = trim($_GET["estado"] ?? "");
$isAdmin = is_admin();
$adminName = trim($_SESSION["admin_user"] ?? "Administracion");
if ($adminName === "") {
    $adminName = "Administracion";
}
$toast = null;

if (!empty($_GET["ok"])) {
    $toast = [
        "title" => "Productos",
        "body" => "Producto guardado correctamente.",
        "variant" => "success"
    ];
} elseif (!empty($_GET["deleted"])) {
    $toast = [
        "title" => "Productos",
        "body" => "Producto eliminado correctamente.",
        "variant" => "danger"
    ];
} elseif (!empty($_GET["updated"])) {
    $toast = [
        "title" => "Productos",
        "body" => "Producto actualizado correctamente.",
        "variant" => "success"
    ];
} elseif ($estadoStatus !== "") {
    if ($estadoStatus === "pausado") {
        $toast = [
            "title" => "Productos",
            "body" => "Producto pausado correctamente.",
            "variant" => "warning"
        ];
    } elseif ($estadoStatus === "activo") {
        $toast = [
            "title" => "Productos",
            "body" => "Producto reanudado correctamente.",
            "variant" => "success"
        ];
    } elseif ($estadoStatus === "finalizado") {
        $toast = [
            "title" => "Productos",
            "body" => "No puedes pausar un producto finalizado.",
            "variant" => "warning"
        ];
    } elseif ($estadoStatus === "retirado") {
        $toast = [
            "title" => "Productos",
            "body" => "Producto retirado de la subasta.",
            "variant" => "warning"
        ];
    } elseif ($estadoStatus === "fechas") {
        $toast = [
            "title" => "Productos",
            "body" => "Las fechas deben ser hoy o futuro y la fecha fin debe ser posterior al inicio.",
            "variant" => "danger"
        ];
    } else {
        $toast = [
            "title" => "Productos",
            "body" => "No se pudo actualizar el estado.",
            "variant" => "danger"
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Administracion - Productos</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@400;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="../css/style.css" rel="stylesheet" />
</head>
<body class="auth-page products-page">
    <?php if ($toast) { ?>
        <div class="toast-container position-fixed top-0 end-0 p-3">
            <div id="statusToast" class="toast toast-pink" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="4500">
                <div class="toast-header">
                    <strong class="me-auto">Pasistos de luz</strong>
                    <small>Ahora</small>
                    <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body">
                    <?php echo htmlspecialchars($toast["body"] ?? ""); ?>
                </div>
            </div>
        </div>
    <?php } ?>
    <main class="auth admin-layout">
        <div class="panel-actions">
            <a class="btn btn-compact ghost" href="subasta.php">Volver a subasta</a>
            <?php if ($isAdmin) { ?>
                <a class="btn btn-compact ghost" href="graficos.php">Graficas</a>
                <a class="btn btn-compact" href="dashboard.php">Ir al dashboard</a>
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
            <label class="checkbox">
                <input type="checkbox" name="finalizados" value="1" <?php echo $mostrarFinalizados ? "checked" : ""; ?> onchange="this.form.submit()" />
                Mostrar finalizados
            </label>
        </form>

        <div class="modal fade" id="reactivarModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Reactivar subasta</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Quieres reactivar la subasta de <strong id="reactivarNombre"></strong> para una nueva subasta?</p>
                        <div class="auth-form mt-3">
                            <label class="field">
                                <span>Fecha y hora de inicio</span>
                                <input id="reactivarInicio" name="fecha_inicio" type="datetime-local" required form="reactivarForm" />
                            </label>
                            <label class="field">
                                <span>Fecha y hora de fin</span>
                                <input id="reactivarFin" name="fecha_fin" type="datetime-local" required form="reactivarForm" />
                                <small class="field-hint">La fecha fin debe ser posterior al inicio.</small>
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <form id="reactivarForm" action="cambiar_estado_producto.php" method="post">
                            <input type="hidden" name="id" id="reactivarId" value="" />
                            <input type="hidden" name="estado" value="activo" />
                            <button type="submit" class="btn">Reactivar</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <section class="auth-card admin-new">
            <div class="auth-brand">
                <div class="brand-mark">A</div>
                <div>
                    <div class="brand-name">Hola <?php echo htmlspecialchars($adminName); ?></div>
                    <div class="brand-tag">Agregar producto</div>
                </div>
            </div>
            <h1>Nuevo producto</h1>
            <p class="lead">Completa los datos para publicar una nueva subasta.</p>
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
                <a class="link" href="panel.php">Volver al panel</a>
            </div>
        </section>

        <section class="auth-card admin-products">
            <div class="section-header">
                <h2 class="section-title">Productos existentes</h2>
                <div class="action-row">
                    <a class="btn btn-small btn-outline" href="export_productos.php?format=excel">Exportar Excel</a>
                    <a class="btn btn-small btn-outline" href="export_productos.php?format=pdf">Exportar PDF</a>
                </div>
            </div>
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
                                            <?php if ($isAdmin) { ?>
                                                <?php if (($producto["estado"] ?? "") === "finalizado") { ?>
                                                    <button class="btn btn-small btn-outline" type="button" data-reactivar="1" data-id="<?php echo (int) $producto["id"]; ?>" data-name="<?php echo htmlspecialchars($producto["nombre"] ?? ""); ?>">Reactivar</button>
                                                <?php } ?>
                                                <?php if (($producto["estado"] ?? "") === "activo") { ?>
                                                    <form action="cambiar_estado_producto.php" method="post" onsubmit="return confirm('Quieres pausar este producto?');">
                                                        <input type="hidden" name="id" value="<?php echo (int) $producto["id"]; ?>" />
                                                        <input type="hidden" name="estado" value="pausado" />
                                                        <button class="btn btn-small btn-outline" type="submit">Pausar</button>
                                                    </form>
                                                <?php } elseif (($producto["estado"] ?? "") === "pausado") { ?>
                                                    <form action="cambiar_estado_producto.php" method="post" onsubmit="return confirm('Quieres reanudar este producto?');">
                                                        <input type="hidden" name="id" value="<?php echo (int) $producto["id"]; ?>" />
                                                        <input type="hidden" name="estado" value="activo" />
                                                        <button class="btn btn-small btn-outline" type="submit">Reanudar</button>
                                                    </form>
                                                <?php } ?>
                                                <a class="btn btn-small btn-outline" href="editar_producto.php?id=<?php echo (int) $producto["id"]; ?>">Editar</a>
                                                <form action="retirar_producto.php" method="post" onsubmit="return confirm('Quieres retirar este producto de la subasta?');">
                                                    <input type="hidden" name="id" value="<?php echo (int) $producto["id"]; ?>" />
                                                    <button class="btn btn-small" type="submit">Retirar</button>
                                                </form>
                                            <?php } else { ?>
                                                <span class="field-hint">Solo admin</span>
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
            var modalEl = document.getElementById("reactivarModal");
            if (!modalEl) {
                return;
            }
            var modal = new bootstrap.Modal(modalEl);
            var idInput = document.getElementById("reactivarId");
            var nameLabel = document.getElementById("reactivarNombre");
            var inicioInput = document.getElementById("reactivarInicio");
            var finInput = document.getElementById("reactivarFin");
            var form = document.getElementById("reactivarForm");

            function pad(value) {
                return String(value).padStart(2, "0");
            }

            function nowLocalValue() {
                var now = new Date();
                return (
                    now.getFullYear() +
                    "-" +
                    pad(now.getMonth() + 1) +
                    "-" +
                    pad(now.getDate()) +
                    "T" +
                    pad(now.getHours()) +
                    ":" +
                    pad(now.getMinutes())
                );
            }

            function syncMinDates() {
                if (!inicioInput || !finInput) {
                    return;
                }
                var minNow = nowLocalValue();
                if (!inicioInput.value || inicioInput.value < minNow) {
                    inicioInput.min = minNow;
                }
                if (inicioInput.value) {
                    finInput.min = inicioInput.value;
                } else {
                    finInput.min = minNow;
                }
            }

            document.querySelectorAll("button[data-reactivar='1']").forEach(function (btn) {
                btn.addEventListener("click", function () {
                    var id = btn.getAttribute("data-id") || "";
                    var name = btn.getAttribute("data-name") || "";
                    if (idInput) {
                        idInput.value = id;
                    }
                    if (nameLabel) {
                        nameLabel.textContent = name;
                    }
                    if (inicioInput && finInput) {
                        var minNow = nowLocalValue();
                        inicioInput.value = "";
                        finInput.value = "";
                        inicioInput.min = minNow;
                        finInput.min = minNow;
                    }
                    modal.show();
                });
            });

            if (inicioInput && finInput) {
                inicioInput.addEventListener("change", syncMinDates);
            }

            if (form) {
                form.addEventListener("submit", function (event) {
                    if (!inicioInput || !finInput) {
                        return;
                    }
                    var inicio = inicioInput.value;
                    var fin = finInput.value;
                    var minNow = nowLocalValue();
                    if (!inicio || !fin || inicio < minNow || fin <= inicio) {
                        event.preventDefault();
                        finInput.setCustomValidity("La fecha fin debe ser posterior al inicio y no en el pasado.");
                        finInput.reportValidity();
                        finInput.setCustomValidity("");
                    }
                });
            }
        })();
    </script>
</body>
</html>
