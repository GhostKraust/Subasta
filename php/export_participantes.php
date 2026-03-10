<?php
require_once __DIR__ . "/auth.php";
require_admin();
require_once __DIR__ . "/../config/db.php";

$format = strtolower(trim($_GET["format"] ?? "csv"));
if (!in_array($format, ["csv", "pdf", "excel"], true)) {
    http_response_code(400);
    exit("Formato no valido.");
}

$fromRaw = trim($_GET["from"] ?? "");
$toRaw = trim($_GET["to"] ?? "");
$categoriaFiltro = (int) ($_GET["categoria"] ?? 0);
$productoFiltro = trim($_GET["producto"] ?? "");
$participanteFiltro = trim($_GET["participante"] ?? "");

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

$query = "SELECT p.nombre AS producto_nombre, c.nombre AS categoria_nombre, pu.nombre_usuario, pu.correo_usuario, pu.telefono_usuario, pu.monto_puja, pu.fecha_puja
    FROM pujas pu
    JOIN productos p ON pu.producto_id = p.id
    LEFT JOIN categorias c ON p.categoria_id = c.id
    WHERE 1=1";

$params = [];
$types = "";

if ($fromSql !== "") {
    $query .= " AND pu.fecha_puja >= ?";
    $params[] = $fromSql;
    $types .= "s";
}
if ($toSql !== "") {
    $query .= " AND pu.fecha_puja <= ?";
    $params[] = $toSql;
    $types .= "s";
}
if ($categoriaFiltro > 0) {
    $query .= " AND p.categoria_id = ?";
    $params[] = $categoriaFiltro;
    $types .= "i";
}
if ($productoFiltro !== "") {
    $query .= " AND p.nombre LIKE ?";
    $params[] = "%" . $productoFiltro . "%";
    $types .= "s";
}
if ($participanteFiltro !== "") {
    $query .= " AND pu.nombre_usuario LIKE ?";
    $params[] = "%" . $participanteFiltro . "%";
    $types .= "s";
}

$query .= " ORDER BY p.nombre ASC, pu.fecha_puja DESC";

$rows = [];
$stmt = $mysqli->prepare($query);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    $stmt->close();
}

$headers = [
    "Producto",
    "Categoria",
    "Participante",
    "Correo",
    "Telefono",
    "Monto Puja (MXN)",
    "Fecha Puja"
];

function writeCsvRow($output, $row, $delimiter)
{
    $temp = fopen("php://temp", "r+");
    fputcsv($temp, $row, $delimiter);
    rewind($temp);
    $csv = stream_get_contents($temp);
    fclose($temp);
    $csv = rtrim($csv, "\r\n") . "\r\n";
    fwrite($output, $csv);
}

if ($format === "csv") {
    $filename = "participantes-" . date("Ymd") . ".csv";
    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");

    $output = fopen("php://output", "w");
    fwrite($output, "\xEF\xBB\xBF");
    $delimiter = ";";
    writeCsvRow($output, $headers, $delimiter);

    foreach ($rows as $row) {
        $line = [
            $row["producto_nombre"] ?? "",
            $row["categoria_nombre"] ?? "",
            $row["nombre_usuario"] ?? "",
            $row["correo_usuario"] ?? "",
            $row["telefono_usuario"] ?? "",
            $row["monto_puja"] ?? "",
            $row["fecha_puja"] ?? ""
        ];
        writeCsvRow($output, $line, $delimiter);
    }

    fclose($output);
    exit;
}
$isExcel = $format === "excel";
if ($isExcel) {
    $filename = "participantes-" . date("Ymd") . ".xls";
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    header("Cache-Control: max-age=0");
}

$generatedAt = date("Y-m-d H:i");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Exportar Participantes</title>
    <style>
        :root { color-scheme: light; }
        body { margin: 24px; font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; color: #1f1f1f; }
        header { display: flex; align-items: baseline; justify-content: space-between; gap: 16px; margin-bottom: 16px; }
        h1 { font-size: 1.4rem; margin: 0; }
        .meta { color: #555; font-size: 0.9rem; }
        .header-actions { display: flex; gap: 12px; align-items: center; }
        .btn-back { display: none; padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.9rem; text-decoration: none; align-items: center; gap: 6px; }
        @media print { .btn-back { display: none !important; } }
        @media screen { .btn-back { display: inline-flex !important; } }
        .hint { margin: 6px 0 16px; color: #555; font-size: 0.9rem; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th, td { border: 1px solid #c9c9c9; padding: 8px 10px; text-align: left; vertical-align: top; word-break: break-word; }
        th { background: #f1f1f1; }
        @page { size: landscape; margin: 12mm; }
        @media print { body { margin: 12mm; } }
    </style>
</head>
<body>
    <header>
        <div>
            <h1>Participantes de Subasta</h1>
        </div>
        <div class="header-actions">
            <a href="participantes.php" class="btn-back">← Volver</a>
            <div class="meta">Generado: <?php echo htmlspecialchars($generatedAt); ?></div>
        </div>
    </header>
    <?php if (!$isExcel) { ?>
        <script>
            window.addEventListener("load", function () { window.print(); });
        </script>
    <?php } ?>
    <table>
        <thead>
            <tr>
                <?php foreach ($headers as $head) { ?>
                    <th><?php echo htmlspecialchars($head); ?></th>
                <?php } ?>
            </tr>
        </thead>
        <tbody>
            <?php if (count($rows) === 0) { ?>
                <tr>
                    <td colspan="<?php echo count($headers); ?>">No hay participantes para los filtros seleccionados.</td>
                </tr>
            <?php } else { ?>
                <?php foreach ($rows as $row) { ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row["producto_nombre"] ?? ""); ?></td>
                        <td><?php echo htmlspecialchars($row["categoria_nombre"] ?? ""); ?></td>
                        <td><?php echo htmlspecialchars($row["nombre_usuario"] ?? ""); ?></td>
                        <td><?php echo htmlspecialchars($row["correo_usuario"] ?? ""); ?></td>
                        <td><?php echo htmlspecialchars($row["telefono_usuario"] ?? ""); ?></td>
                        <td><?php echo htmlspecialchars(number_format((float)($row["monto_puja"] ?? 0), 2)); ?></td>
                        <td><?php echo htmlspecialchars($row["fecha_puja"] ?? ""); ?></td>
                    </tr>
                <?php } ?>
            <?php } ?>
        </tbody>
    </table>
</body>
</html>