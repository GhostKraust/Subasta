<?php
require_once __DIR__ . "/auth.php";
require_admin();
require_once __DIR__ . "/../config/db.php";

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
$productoFiltro = trim($_GET["producto"] ?? "");
$participanteFiltro = trim($_GET["participante"] ?? "");

// Paginacion
$limit = 4;
$page = max(1, (int) ($_GET["page"] ?? 1));

$fromDate = null;
$toDate = null;
if ($fromRaw !== "") {
    $parsed = DateTime::createFromFormat("Y-m-d", $fromRaw);
    if ($parsed) $fromDate = $parsed;
}
if ($toRaw !== "") {
    $parsed = DateTime::createFromFormat("Y-m-d", $toRaw);
    if ($parsed) $toDate = $parsed;
}
if ($fromDate && $toDate && $fromDate > $toDate) {
    list($fromDate, $toDate) = [$toDate, $fromDate];
}

$fromSql = $fromDate ? $fromDate->format("Y-m-d 00:00:00") : "";
$toSql = $toDate ? $toDate->format("Y-m-d 23:59:59") : "";

$baseQuery = "FROM pujas pu
    JOIN productos p ON pu.producto_id = p.id
    LEFT JOIN categorias c ON p.categoria_id = c.id
    WHERE 1=1";

$params = [];
$types = "";

if ($fromSql !== "") {
    $baseQuery .= " AND pu.fecha_puja >= ?";
    $params[] = $fromSql;
    $types .= "s";
}
if ($toSql !== "") {
    $baseQuery .= " AND pu.fecha_puja <= ?";
    $params[] = $toSql;
    $types .= "s";
}
if ($categoriaFiltro > 0) {
    $baseQuery .= " AND p.categoria_id = ?";
    $params[] = $categoriaFiltro;
    $types .= "i";
}
if ($productoFiltro !== "") {
    $baseQuery .= " AND p.nombre LIKE ?";
    $params[] = "%" . $productoFiltro . "%";
    $types .= "s";
}
if ($participanteFiltro !== "") {
    $baseQuery .= " AND pu.nombre_usuario LIKE ?";
    $params[] = "%" . $participanteFiltro . "%";
    $types .= "s";
}

// Contar total de registros
$totalRecords = 0;
$stmtCount = $mysqli->prepare("SELECT COUNT(*) AS total " . $baseQuery);
if ($stmtCount) {
    if (!empty($params)) {
        $stmtCount->bind_param($types, ...$params);
    }
    $stmtCount->execute();
    $resCount = $stmtCount->get_result();
    if ($resCount) {
        $row = $resCount->fetch_assoc();
        $totalRecords = (int) ($row["total"] ?? 0);
    }
    $stmtCount->close();
}

$totalPages = max(1, ceil($totalRecords / $limit));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $limit;

// Consulta principal con limite
$query = "SELECT p.nombre AS producto_nombre, c.nombre AS categoria_nombre, pu.nombre_usuario, pu.correo_usuario, pu.telefono_usuario, pu.monto_puja, pu.fecha_puja " . $baseQuery;
$query .= " ORDER BY p.nombre ASC, pu.fecha_puja DESC LIMIT ? OFFSET ?";

$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$participantes = [];
$stmt = $mysqli->prepare($query);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $participantes[] = $row;
        }
    }
    $stmt->close();
}

$exportParams = [];
if ($fromRaw !== "") $exportParams[] = "from=" . urlencode($fromRaw);
if ($toRaw !== "") $exportParams[] = "to=" . urlencode($toRaw);
if ($categoriaFiltro > 0) $exportParams[] = "categoria=" . $categoriaFiltro;
if ($productoFiltro !== "") $exportParams[] = "producto=" . urlencode($productoFiltro);
if ($participanteFiltro !== "") $exportParams[] = "participante=" . urlencode($participanteFiltro);
$exportQuery = count($exportParams) > 0 ? "&" . implode("&", $exportParams) : "";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Administracion - Participantes</title>
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
                <div class="brand-tag">Lista de Participantes</div>
            </div>
        </div>
        <div class="dash-actions">
            <a class="btn btn-compact ghost" href="export_participantes.php?format=excel<?php echo $exportQuery; ?>">Exportar Excel</a>
            <a class="btn btn-compact ghost" href="export_participantes.php?format=pdf<?php echo $exportQuery; ?>">Exportar PDF</a>
            <a class="btn btn-compact" href="dashboard.php">Inicio</a>
        </div>
    </header>

    <main class="dashboard">
        <section class="card">
            <div class="card-title">Filtros</div>
            <form class="filter-form" method="get">
                <div class="filter-grid">
                    <label class="field">
                        <span>Producto</span>
                        <input type="text" name="producto" value="<?php echo htmlspecialchars($productoFiltro); ?>" placeholder="Nombre del producto" />
                    </label>
                    <label class="field">
                        <span>Participante</span>
                        <input type="text" name="participante" value="<?php echo htmlspecialchars($participanteFiltro); ?>" placeholder="Nombre del participante" />
                    </label>
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
                    <button class="btn btn-compact" type="submit">Aplicar filtros</button>
                    <a class="btn btn-compact ghost" href="participantes.php">Limpiar</a>
                </div>
            </form>
        </section>

        <section class="card">
            <div class="card-title">Lista de Participantes por Producto</div>
            <div class="table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Participante</th>
                            <th>Correo</th>
                            <th>Telefono</th>
                            <th>Monto de Puja</th>
                            <th>Fecha de Puja</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($participantes) === 0) { ?>
                            <tr>
                                <td colspan="6">No hay participantes para los filtros seleccionados.</td>
                            </tr>
                        <?php } else { ?>
                            <?php foreach ($participantes as $row) { ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row["producto_nombre"] ?? ""); ?></td>
                                    <td><?php echo htmlspecialchars($row["nombre_usuario"] ?? ""); ?></td>
                                    <td style="word-break: break-all;"><?php echo htmlspecialchars($row["correo_usuario"] ?? "-"); ?></td>
                                    <td><?php echo htmlspecialchars($row["telefono_usuario"] ?? "-"); ?></td>
                                    <td>$<?php echo number_format((float) ($row["monto_puja"] ?? 0), 2); ?></td>
                                    <td><?php echo htmlspecialchars($row["fecha_puja"] ?? "-"); ?></td>
                                </tr>
                            <?php } ?>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php if ($totalPages > 1) { ?>
                <div class="pagination">
                    <a class="btn btn-compact <?php echo $page <= 1 ? 'disabled' : ''; ?>" href="<?php echo $page <= 1 ? '#' : '?page=' . ($page - 1) . $exportQuery; ?>">Anterior</a>
                    <span class="page-info">Pagina <?php echo $page; ?> de <?php echo $totalPages; ?></span>
                    <a class="btn btn-compact <?php echo $page >= $totalPages ? 'disabled' : ''; ?>" href="<?php echo $page >= $totalPages ? '#' : '?page=' . ($page + 1) . $exportQuery; ?>">Siguiente</a>
                </div>
            <?php } ?>
        </section>
    </main>
</body>
</html>