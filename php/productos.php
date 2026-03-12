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

$hasExpiracion = false;
$checkExp = $mysqli->query("SHOW COLUMNS FROM productos LIKE 'fecha_expiracion'");
if ($checkExp && $checkExp->num_rows > 0) {
    $hasExpiracion = true;
}

$hasExpiracion = false;
$checkExp = $mysqli->query("SHOW COLUMNS FROM productos LIKE 'fecha_expiracion'");
if ($checkExp && $checkExp->num_rows > 0) {
    $hasExpiracion = true;
}

$monedas = ["MXN", "USD", "CAD"];
$moneda = strtoupper($_GET["moneda"] ?? "MXN");
if (!in_array($moneda, $monedas, true)) {
    $moneda = "MXN";
}

$mostrarFinalizados = !empty($_GET["finalizados"]);
$limit = 4;
$page = max(1, (int) ($_GET["page"] ?? 1));

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

$whereClause = "";
if (!$mostrarFinalizados) {
    $whereClause = " WHERE p.estado <> 'finalizado'";
}

$totalRecords = 0;
$resCount = $mysqli->query("SELECT COUNT(*) as total FROM productos p " . $whereClause);
if ($resCount) {
    $row = $resCount->fetch_assoc();
    $totalRecords = (int) ($row["total"] ?? 0);
}

$totalPages = max(1, ceil($totalRecords / $limit));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $limit;

$queryProductos = "SELECT p.id, p.nombre, p.imagen_url, p.estado, p.$precioColumn AS precio, c.nombre AS categoria$selectIncremento$selectInicio$selectFin " .
    "FROM productos p LEFT JOIN categorias c ON p.categoria_id = c.id" . $whereClause . " ORDER BY p.id DESC LIMIT $limit OFFSET $offset";
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

$pageParams = [];
if ($moneda !== "MXN") {
    $pageParams[] = "moneda=" . urlencode($moneda);
}
if ($mostrarFinalizados) {
    $pageParams[] = "finalizados=1";
}
$pageQuery = count($pageParams) > 0 ? "&" . implode("&", $pageParams) : "";

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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link href="../css/style.css" rel="stylesheet" />
    <style>
        /* Contenedor para limitar el scroll sticky de la vista previa */
        .product-creation-group {
            display: grid;
            grid-column: 1 / -1; /* Hacer que el grupo ocupe todo el ancho */
            grid-template-columns: 1fr 340px; /* Espacio para formulario y preview reducido */
            gap: 32px;
            align-items: start;
            margin-bottom: 40px; /* Separacion con la tabla de abajo */
        }
        .product-preview-wrapper {
            position: sticky;
            top: 24px; /* Margen superior al bajar */
        }
        @media (max-width: 1100px) {
            .product-creation-group { display: block; }
            .product-preview-wrapper { display: none; } /* En movil se usa el boton de modal */
        }

        /* --- DISEÑO AISLADO PARA TABLA DE PRODUCTOS --- */
        .products-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 10px;
            font-size: 0.9rem;
        }

        .products-table th {
            text-align: left;
            padding: 16px;
            background: #f8fafc;
            color: #64748b;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            border-bottom: 2px solid #e2e8f0;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .products-table td {
            padding: 16px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            color: #334155;
            background: #ffffff;
        }

        .products-table tbody tr:hover td {
            background-color: #f8fafc;
        }

        .thumb {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid #e2e8f0;
        }

        /* RESPONSIVE: Vista de Tarjetas para Celular */
        @media (max-width: 900px) {
            .products-table thead {
                display: none;
            }
            
            .products-table, .products-table tbody, .products-table tr, .products-table td {
                display: block;
                width: 100%;
            }

            .products-table tbody tr {
                margin-bottom: 24px;
                border: 1px solid #e2e8f0;
                border-radius: 16px;
                background: #ffffff;
                padding: 20px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            }

            .products-table td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                text-align: right;
                padding: 12px 0;
                border-bottom: 1px solid #f1f5f9;
            }

            .products-table td:last-child {
                border-bottom: none;
            }

            .products-table td::before {
                content: attr(data-label);
                font-weight: 700;
                color: #94a3b8;
                font-size: 0.75rem;
                text-transform: uppercase;
                margin-right: 15px;
                text-align: left;
            }

            /* Ajuste especifico para la imagen en movil */
            .products-table td[data-label="Imagen"] { justify-content: center; padding-bottom: 20px; }
            .products-table td[data-label="Imagen"]::before { display: none; }
            .products-table td[data-label="Imagen"] .thumb { width: 100px; height: 100px; }

            /* Ajuste para acciones */
            .products-table td[data-label="Acciones"] { display: block; margin-top: 15px; }
            .products-table td[data-label="Acciones"]::before { display: none; }
            .products-table td[data-label="Acciones"] .action-row { justify-content: center; width: 100%; }
        }
    </style>
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
                <a class="btn btn-compact ghost" href="historial_productos.php">Historial</a>
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
            <label class="checkbox">
                <input type="checkbox" name="finalizados" value="1" <?php echo $mostrarFinalizados ? "checked" : ""; ?> onchange="this.form.submit()" />
                Mostrar finalizados
            </label>
        </form>

        <div class="product-creation-group">
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
                    <input name="imagen[]" type="file" accept="image/*" multiple required />
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
                <label class="field">
                    <span>Fecha de expiración (Validez)</span>
                    <input name="fecha_expiracion" type="datetime-local" />
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
                <button type="button" class="btn btn-preview-mobile" data-bs-toggle="modal" data-bs-target="#previewModal">Ver Vista Previa</button>
            </form>
            <div class="switch">
                <a class="link" href="panel.php">Volver al panel</a>
            </div>
        </section>
        <aside class="product-preview-wrapper">
            <h3 class="preview-title">Vista previa</h3>
            <div class="preview-card">
                <div class="preview-image-box">
                    <img id="preview-image-desktop" src="" alt="Vista previa" style="display: none;" />
                    <div id="preview-placeholder-desktop" style="width:100%; height:100%; display:grid; place-items:center; color:#94a3b8; font-weight:600;">Sin imagen</div>
                    <div id="preview-category-desktop" class="preview-category-tag">Categoria</div>
                </div>
                <div class="preview-info">
                    <h2 id="preview-name-desktop" class="preview-product-title">Nombre del Producto</h2>
                    <p id="preview-description-desktop" class="preview-product-desc">Aquí va la descripción del producto que estás agregando.</p>
                    <div class="preview-prices">
                        <div class="preview-price-block">
                            <span class="preview-price-label">Precio inicial</span>
                            <span id="preview-price-desktop" class="preview-price-value">MXN $0.00</span>
                        </div>
                        <div class="preview-price-block">
                            <span class="preview-price-label">Puja actual</span>
                            <span id="preview-price-current-desktop" class="preview-price-value highlight">MXN $0.00</span>
                        </div>
                    </div>
                </div>
            </div>
        </aside>
        </div>

        <section class="auth-card admin-products">
            <div class="section-header">
                <h2 class="section-title">Productos existentes</h2>
                <div class="action-row">
                    <a class="btn btn-small btn-outline" href="export_productos.php?format=excel">Exportar Excel</a>
                    <a class="btn btn-small btn-outline" href="export_productos.php?format=pdf">Exportar PDF</a>
                </div>
            </div>
            <div class="table-wrap">
                <table class="products-table">
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
                                    $firstImage = $imagenes[0] ?? "";
                                ?>
                                <tr>
                                    <td data-label="Imagen">
                                        <?php if ($firstImage !== "") { ?>
                                            <img class="thumb" src="<?php echo htmlspecialchars($firstImage); ?>" alt="" />
                                        <?php } ?>
                                    </td>
                                    <td data-label="Producto"><?php echo htmlspecialchars($producto["nombre"] ?? ""); ?></td>
                                    <td data-label="Categoria"><?php echo htmlspecialchars($producto["categoria"] ?? "Sin categoria"); ?></td>
                                    <td data-label="Precio inicial">
                                        <div class="currency-stack">
                                            <?php foreach ($monedas as $code) { ?>
                                                <div class="currency-line">
                                                    <?php echo htmlspecialchars($code . " " . formatCurrency((float) $producto["precio"], $code, $rates)); ?>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    </td>
                                    <?php if ($hasIncremento) { ?>
                                        <td data-label="Incremento">
                                            <div class="currency-stack">
                                                <?php foreach ($monedas as $code) { ?>
                                                    <div class="currency-line">
                                                        <?php echo htmlspecialchars($code . " " . formatCurrency((float) ($producto["incremento_minimo"] ?? 0), $code, $rates)); ?>
                                                    </div>
                                                <?php } ?>
                                            </div>
                                        </td>
                                    <?php } ?>
                                    <td data-label="Estado"><span class="status-tag"><?php echo htmlspecialchars($producto["estado"] ?? ""); ?></span></td>
                                    <td data-label="Ganador">
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
                                    <td data-label="Acciones">
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
            <?php if ($totalPages > 1) { ?>
                <div class="pagination">
                    <a class="btn btn-compact <?php echo $page <= 1 ? 'disabled' : ''; ?>" href="<?php echo $page <= 1 ? '#' : '?page=' . ($page - 1) . $pageQuery; ?>">Anterior</a>
                    <span class="page-info">Pagina <?php echo $page; ?> de <?php echo $totalPages; ?></span>
                    <a class="btn btn-compact <?php echo $page >= $totalPages ? 'disabled' : ''; ?>" href="<?php echo $page >= $totalPages ? '#' : '?page=' . ($page + 1) . $pageQuery; ?>">Siguiente</a>
                </div>
            <?php } ?>
        </section>
    </main>
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

    <!-- Cropper Modal -->
    <div class="modal fade" id="cropperModal" tabindex="-1" aria-labelledby="cropperModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cropperModalLabel">Ajustar Imagen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="cropper-modal-img-container">
                        <img id="cropperImage" src="" alt="Imagen para recortar">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn" id="cropButton">Recortar y Usar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div class="modal fade modal-preview" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="preview-card">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="position: absolute; top: 1rem; right: 1rem; z-index: 10;"></button>
                    <div class="preview-image-box">
                        <img id="preview-image-mobile" src="" alt="Vista previa" style="display: none;" />
                        <div id="preview-placeholder-mobile" style="width:100%; height:100%; display:grid; place-items:center; color:#94a3b8; font-weight:600;">Sin imagen</div>
                        <div id="preview-category-mobile" class="preview-category-tag">Categoria</div>
                    </div>
                    <div class="preview-info">
                        <h2 id="preview-name-mobile" class="preview-product-title">Nombre del Producto</h2>
                        <p id="preview-description-mobile" class="preview-product-desc">Aquí va la descripción del producto que estás agregando.</p>
                        <div class="preview-prices">
                            <div class="preview-price-block">
                                <span class="preview-price-label">Precio inicial</span>
                                <span id="preview-price-mobile" class="preview-price-value">MXN $0.00</span>
                            </div>
                            <div class="preview-price-block">
                                <span class="preview-price-label">Puja actual</span>
                                <span id="preview-price-current-mobile" class="preview-price-value highlight">MXN $0.00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/es.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
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

            const fpConfig = {
                enableTime: true,
                dateFormat: "Y-m-d H:i",
                altInput: true,
                altFormat: "j F, Y h:i K",
                locale: "es",
                minDate: "today",
            };

            const fpInicio = inicioInput ? flatpickr(inicioInput, {
                ...fpConfig,
                onChange: function(selectedDates, dateStr) {
                    if (fpFin) fpFin.set('minDate', dateStr);
                },
            }) : null;

            const fpFin = finInput ? flatpickr(finInput, fpConfig) : null;

            document.querySelectorAll("button[data-reactivar='1']").forEach(function (btn) {
                btn.addEventListener("click", function () {
                    var id = btn.getAttribute("data-id") || "";
                    var name = btn.getAttribute("data-name") || "";
                    if (idInput) idInput.value = id;
                    if (nameLabel) nameLabel.textContent = name;
                    if (fpInicio) fpInicio.clear();
                    if (fpFin) fpFin.clear();
                    modal.show();
                });
            });
        })();
    </script>
    <script>
    (function() {
        // Form inputs
        const form = document.querySelector('form[action="guardar_producto.php"]');
        if (!form) return;

        const nombreInput = form.querySelector('input[name="nombre"]');
        const descripcionInput = form.querySelector('input[name="descripcion"]');
        const imagenInput = form.querySelector('input[name="imagen[]"]');
        const precioInput = form.querySelector('input[name="precio_inicial"]');
        const categoriaSelect = form.querySelector('select[name="categoria_id"]');

        // Desktop Preview elements
        const previewImageDesktop = document.getElementById('preview-image-desktop');
        const previewCategoryDesktop = document.getElementById('preview-category-desktop');
        const previewNameDesktop = document.getElementById('preview-name-desktop');
        const previewDescriptionDesktop = document.getElementById('preview-description-desktop');
        const previewPriceDesktop = document.getElementById('preview-price-desktop');
        const previewPriceCurrentDesktop = document.getElementById('preview-price-current-desktop');
        const previewPlaceholderDesktop = document.getElementById('preview-placeholder-desktop');

        // Mobile Preview elements
        const previewImageMobile = document.getElementById('preview-image-mobile');
        const previewCategoryMobile = document.getElementById('preview-category-mobile');
        const previewNameMobile = document.getElementById('preview-name-mobile');
        const previewDescriptionMobile = document.getElementById('preview-description-mobile');
        const previewPriceMobile = document.getElementById('preview-price-mobile');
        const previewPriceCurrentMobile = document.getElementById('preview-price-current-mobile');
        const previewPlaceholderMobile = document.getElementById('preview-placeholder-mobile');

        function updatePreview() {
            const name = nombreInput.value || 'Nombre del Producto';
            const desc = descripcionInput.value || 'Aquí va la descripción del producto que estás agregando.';
            const priceValue = parseFloat(precioInput.value) || 0;
            const formattedPrice = `MXN $${priceValue.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

            const selectedCategory = categoriaSelect.options[categoriaSelect.selectedIndex];
            const category = (selectedCategory && selectedCategory.value !== "") ? selectedCategory.text : 'Categoría';

            // Update Desktop
            previewNameDesktop.textContent = name;
            previewDescriptionDesktop.textContent = desc;
            previewPriceDesktop.textContent = formattedPrice;
            previewPriceCurrentDesktop.textContent = formattedPrice;
            const catDesktop = document.getElementById('preview-category-desktop');
            if (catDesktop) catDesktop.textContent = category;

            // Update Mobile
            previewNameMobile.textContent = name;
            previewDescriptionMobile.textContent = desc;
            previewPriceMobile.textContent = formattedPrice;
            previewPriceCurrentMobile.textContent = formattedPrice;
            const catMobile = document.getElementById('preview-category-mobile');
            if (catMobile) catMobile.textContent = category;
        }

        [nombreInput, descripcionInput, precioInput].forEach(el => el.addEventListener('input', updatePreview));
        categoriaSelect.addEventListener('change', updatePreview);

        // --- Cropper Logic ---
        const cropperModalEl = document.getElementById('cropperModal');
        const cropperModal = new bootstrap.Modal(cropperModalEl);
        const cropperImage = document.getElementById('cropperImage');
        const cropButton = document.getElementById('cropButton');
        const cropperModalLabel = document.getElementById('cropperModalLabel');
        let cropper;

        let filesToCrop = [];
        let croppedBlobs = [];
        let currentCropIndex = 0;

        function loadCropperForCurrentFile() {
            const file = filesToCrop[currentCropIndex];
            const reader = new FileReader();
            reader.onload = function(event) {
                if (cropper) {
                    cropper.replace(event.target.result);
                } else {
                    cropperImage.src = event.target.result;
                }
                if (cropperModalLabel) {
                    cropperModalLabel.textContent = `Ajustar Imagen (${currentCropIndex + 1} de ${filesToCrop.length})`;
                }
                if (currentCropIndex === 0) {
                    cropperModal.show();
                }
            };
            reader.readAsDataURL(file);
        }

        function finalizeCropping() {
            cropperModal.hide();
            const dataTransfer = new DataTransfer();
            croppedBlobs.forEach((blob, index) => {
                const file = new File([blob], `cropped_image_${index}.jpg`, { type: 'image/jpeg' });
                dataTransfer.items.add(file);
            });
            imagenInput.files = dataTransfer.files;
            updatePreviewWithCroppedImages();
        }

        function updatePreviewWithCroppedImages() {
            const buildCarousel = (targetId, categoryId) => {
                let innerHTML = `<div id="${targetId}" class="carousel slide carousel-dark h-100" data-bs-ride="carousel"><div class="carousel-inner h-100">`;
                croppedBlobs.forEach((blob, index) => {
                    const url = URL.createObjectURL(blob);
                    innerHTML += `<div class="carousel-item h-100 ${index === 0 ? 'active' : ''}"><img src="${url}" class="d-block w-100 h-100" style="object-fit: cover;" alt="Vista previa ${index + 1}"></div>`;
                });
                innerHTML += `</div><button class="carousel-control-prev" type="button" data-bs-target="#${targetId}" data-bs-slide="prev"><span class="carousel-control-prev-icon" aria-hidden="true"></span></button><button class="carousel-control-next" type="button" data-bs-target="#${targetId}" data-bs-slide="next"><span class="carousel-control-next-icon" aria-hidden="true"></span></button><div id="${categoryId}" class="preview-category-tag">Categoria</div></div>`;
                return innerHTML;
            };
            const imageBoxDesktop = document.querySelector('.product-preview-wrapper .preview-image-box');
            const imageBoxMobile = document.querySelector('#previewModal .preview-image-box');
            if (croppedBlobs.length > 1) {
                imageBoxDesktop.innerHTML = buildCarousel('carousel-preview-desktop', 'preview-category-desktop');
                imageBoxMobile.innerHTML = buildCarousel('carousel-preview-mobile', 'preview-category-mobile');
            } else if (croppedBlobs.length === 1) {
                const url = URL.createObjectURL(croppedBlobs[0]);
                imageBoxDesktop.innerHTML = `<img id="preview-image-desktop" src="${url}" alt="Vista previa" style="display: block;" /><div id="preview-placeholder-desktop" style="display:none;">Sin imagen</div><div id="preview-category-desktop" class="preview-category-tag">Categoria</div>`;
                imageBoxMobile.innerHTML = `<img id="preview-image-mobile" src="${url}" alt="Vista previa" style="display: block;" /><div id="preview-placeholder-mobile" style="display:none;">Sin imagen</div><div id="preview-category-mobile" class="preview-category-tag">Categoria</div>`;
            }
            updatePreview();
        }

        imagenInput.addEventListener('change', function(e) {
            filesToCrop = Array.from(e.target.files);
            if (filesToCrop.length > 0) {
                currentCropIndex = 0;
                croppedBlobs = [];
                loadCropperForCurrentFile();
            }
        });

        cropperModalEl.addEventListener('shown.bs.modal', function() {
            if (cropper) cropper.destroy();
            cropper = new Cropper(cropperImage, {
                aspectRatio: 4 / 3,
                viewMode: 1,
                dragMode: 'move',
                autoCropArea: 0.9,
                restore: false,
                guides: true,
                center: true,
                highlight: false,
                cropBoxMovable: true,
                cropBoxResizable: true,
                toggleDragModeOnDblclick: false,
            });
        });

        cropperModalEl.addEventListener('hidden.bs.modal', function() {
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
        });

        cropButton.addEventListener('click', function() {
            if (!cropper) return;
            const canvas = cropper.getCroppedCanvas({ width: 800, height: 600, imageSmoothingQuality: 'high' });
            canvas.toBlob(function(blob) {
                croppedBlobs.push(blob);
                currentCropIndex++;
                if (currentCropIndex < filesToCrop.length) {
                    loadCropperForCurrentFile();
                } else {
                    finalizeCropping();
                }
            }, 'image/jpeg', 0.9);
        });

        updatePreview();
    })();

    (function() {
        const fpConfig = {
            enableTime: true,
            dateFormat: "Y-m-d H:i",
            altInput: true,
            altFormat: "j F, Y h:i K",
            locale: "es",
            minuteIncrement: 1,
            minDate: "today",
        };

        const newInicio = document.querySelector('form[action="guardar_producto.php"] input[name="fecha_inicio"]');
        const newFin = document.querySelector('form[action="guardar_producto.php"] input[name="fecha_fin"]');

        if (newInicio) {
            const fpInicio = flatpickr(newInicio, {
                ...fpConfig,
                onChange: function(selectedDates, dateStr) {
                    if (fpFin) fpFin.set('minDate', dateStr);
                }
            });
            const fpFin = newFin ? flatpickr(newFin, fpConfig) : null;
        }
    })();
    </script>
</body>
</html>
