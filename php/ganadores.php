<?php
require_once __DIR__ . "/auth.php";
require_admin();
require_once __DIR__ . "/../config/db.php";

$hasFin = false;
$checkFin = $mysqli->query("SHOW COLUMNS FROM productos LIKE 'fecha_fin'");
if ($checkFin && $checkFin->num_rows > 0) {
    $hasFin = true;
}

$categorias = [];
$resultCategorias = $mysqli->query("SELECT id, nombre FROM categorias ORDER BY nombre ASC");
if ($resultCategorias) {
    while ($row = $resultCategorias->fetch_assoc()) {
        $categorias[] = $row;
    }
}

$fromRaw = trim($_GET["from"] ?? "");
$toRaw = trim($_GET["to"] ?? "");
$categoriaFiltro = (int) ($_GET["categoria"] ?? 0);

$fromDate = null;
$toDate = null;
if ($fromRaw !== "") {
    $parsed = DateTime::createFromFormat("Y-m-d", $fromRaw);
    if ($parsed) {
        $fromDate = $parsed;
    }
}
if ($toRaw !== "") {
    $parsed = DateTime::createFromFormat("Y-m-d", $toRaw);
    if ($parsed) {
        $toDate = $parsed;
    }
}
if ($fromDate && $toDate && $fromDate > $toDate) {
    $temp = $fromDate;
    $fromDate = $toDate;
    $toDate = $temp;
}

$fromSql = $fromDate ? $fromDate->format("Y-m-d 00:00:00") : "";
$toSql = $toDate ? $toDate->format("Y-m-d 23:59:59") : "";

$selectFin = $hasFin ? ", p.fecha_fin" : "";
$condFinal = "p.estado = 'finalizado'";
if ($hasFin) {
    $condFinal .= " OR (p.fecha_fin IS NOT NULL AND p.fecha_fin < NOW())";
}

$query = "SELECT p.id, p.nombre$selectFin, p.estado,
    w.nombre_usuario, w.correo_usuario, w.telefono_usuario, w.monto_puja, w.fecha_puja
    FROM productos p
    LEFT JOIN (
        SELECT pu1.*
        FROM pujas pu1
        INNER JOIN (
            SELECT producto_id, MAX(monto_puja) AS max_monto
            FROM pujas
            GROUP BY producto_id
        ) mx ON pu1.producto_id = mx.producto_id AND pu1.monto_puja = mx.max_monto
        INNER JOIN (
            SELECT producto_id, MAX(fecha_puja) AS max_fecha
            FROM pujas
            GROUP BY producto_id
        ) mf ON pu1.producto_id = mf.producto_id AND pu1.fecha_puja = mf.max_fecha
    ) w ON w.producto_id = p.id
    WHERE $condFinal";

$dateField = $hasFin ? "p.fecha_fin" : "w.fecha_puja";
if ($fromSql !== "") {
    $query .= " AND $dateField >= '" . $mysqli->real_escape_string($fromSql) . "'";
}
if ($toSql !== "") {
    $query .= " AND $dateField <= '" . $mysqli->real_escape_string($toSql) . "'";
}
if ($categoriaFiltro > 0) {
    $query .= " AND p.categoria_id = " . $categoriaFiltro;
}

$query .= " ORDER BY " . ($hasFin ? "p.fecha_fin DESC, " : "") . "p.id DESC";

$ganadores = [];
$result = $mysqli->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $ganadores[] = $row;
    }
}

$ganadoresCount = [];
$ganadoresTotal = [];
foreach ($ganadores as $row) {
    $nombre = trim((string) ($row["nombre_usuario"] ?? ""));
    if ($nombre === "") {
        continue;
    }
    $ganadoresCount[$nombre] = ($ganadoresCount[$nombre] ?? 0) + 1;
    $ganadoresTotal[$nombre] = ($ganadoresTotal[$nombre] ?? 0) + (float) ($row["monto_puja"] ?? 0);
}
arsort($ganadoresCount);
arsort($ganadoresTotal);
$topGanadoresCount = array_slice($ganadoresCount, 0, 5, true);
$topGanadoresTotal = array_slice($ganadoresTotal, 0, 5, true);

$exportParams = [];
if ($fromRaw !== "") {
    $exportParams[] = "from=" . urlencode($fromRaw);
}
if ($toRaw !== "") {
    $exportParams[] = "to=" . urlencode($toRaw);
}
if ($categoriaFiltro > 0) {
    $exportParams[] = "categoria=" . $categoriaFiltro;
}
$exportQuery = count($exportParams) > 0 ? "&" . implode("&", $exportParams) : "";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Administracion - Ganadores</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@400;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link href="../css/style.css" rel="stylesheet" />
    <link href="../css/dashboard.css" rel="stylesheet" />
</head>
<body class="auth-page">
    <header class="dash-header">
        <div class="dash-title">
            <div class="brand-mark">A</div>
            <div>
                <div class="brand-name">Administracion</div>
                <div class="brand-tag">Ganadores de subastas</div>
            </div>
        </div>
        <div class="dash-actions">
            <a class="btn ghost" href="export_ganadores.php?format=excel<?php echo $exportQuery; ?>">Exportar Excel</a>
            <a class="btn ghost" href="export_ganadores.php?format=pdf<?php echo $exportQuery; ?>" target="_blank" rel="noopener">Exportar PDF</a>
            <a class="btn ghost" href="dashboard.php">Volver al dashboard</a>
            <a class="btn" href="panel.php">Panel</a>
        </div>
    </header>

    <main class="dashboard">
        <section class="card">
            <div class="card-title">Filtros</div>
            <form class="filter-form" method="get">
                <div class="filter-grid">
                    <label class="field">
                        <span>Desde</span>
                        <input type="date" name="from" value="<?php echo htmlspecialchars($fromRaw); ?>" />
                    </label>
                    <label class="field">
                        <span>Hasta</span>
                        <input type="date" name="to" value="<?php echo htmlspecialchars($toRaw); ?>" />
                    </label>
                    <label class="field">
                        <span>Categoria</span>
                        <select name="categoria">
                            <option value="0">Todas</option>
                            <?php foreach ($categorias as $categoria) { ?>
                                <option value="<?php echo (int) $categoria["id"]; ?>" <?php echo ((int) $categoriaFiltro === (int) $categoria["id"]) ? "selected" : ""; ?>>
                                    <?php echo htmlspecialchars($categoria["nombre"]); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </label>
                </div>
                <div class="filter-actions">
                    <button class="btn" type="submit">Aplicar filtros</button>
                    <a class="btn ghost" href="ganadores.php">Limpiar</a>
                </div>
            </form>
        </section>

        <section class="card">
            <div class="card-title">Top ganadores</div>
            <div class="stats-grid">
                <div>
                    <div class="stat-title">Por numero de ganadas</div>
                    <?php if (count($topGanadoresCount) === 0) { ?>
                        <div class="stat-empty">Sin datos</div>
                    <?php } else { ?>
                        <ul class="stat-list">
                            <?php foreach ($topGanadoresCount as $name => $count) { ?>
                                <li><?php echo htmlspecialchars($name); ?> <span><?php echo (int) $count; ?></span></li>
                            <?php } ?>
                        </ul>
                    <?php } ?>
                </div>
                <div>
                    <div class="stat-title">Por monto total</div>
                    <?php if (count($topGanadoresTotal) === 0) { ?>
                        <div class="stat-empty">Sin datos</div>
                    <?php } else { ?>
                        <ul class="stat-list">
                            <?php foreach ($topGanadoresTotal as $name => $total) { ?>
                                <li><?php echo htmlspecialchars($name); ?> <span>$<?php echo number_format((float) $total, 2); ?></span></li>
                            <?php } ?>
                        </ul>
                    <?php } ?>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card-title">Subastas cerradas</div>
            <div class="table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <?php if ($hasFin) { ?>
                                <th>Fecha fin</th>
                            <?php } ?>
                            <th>Ganador</th>
                            <th>Correo</th>
                            <th>Telefono</th>
                            <th>Monto</th>
                            <th>Fecha puja</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($ganadores) === 0) { ?>
                            <tr>
                                <td colspan="<?php echo $hasFin ? 7 : 6; ?>">No hay subastas finalizadas.</td>
                            </tr>
                        <?php } else { ?>
                            <?php foreach ($ganadores as $row) { ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row["nombre"] ?? ""); ?></td>
                                    <?php if ($hasFin) { ?>
                                        <td><?php echo htmlspecialchars($row["fecha_fin"] ?? "-"); ?></td>
                                    <?php } ?>
                                    <td><?php echo htmlspecialchars($row["nombre_usuario"] ?? "Sin pujas"); ?></td>
                                    <td><?php echo htmlspecialchars($row["correo_usuario"] ?? "-"); ?></td>
                                    <td><?php echo htmlspecialchars($row["telefono_usuario"] ?? "-"); ?></td>
                                    <td>$<?php echo number_format((float) ($row["monto_puja"] ?? 0), 2); ?></td>
                                    <td><?php echo htmlspecialchars($row["fecha_puja"] ?? "-"); ?></td>
                                </tr>
                            <?php } ?>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>
