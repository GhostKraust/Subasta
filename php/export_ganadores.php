<?php
require_once __DIR__ . "/auth.php";
require_admin();
require_once __DIR__ . "/../config/db.php";

$format = strtolower(trim($_GET["format"] ?? "csv"));
if (!in_array($format, ["csv", "pdf", "excel"], true)) {
    http_response_code(400);
    exit("Formato no valido.");
}

$hasFin = true;

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

$query = "SELECT gh.producto_id AS id, gh.producto_nombre AS nombre, gh.fecha_cierre AS fecha_fin, " .
    "'finalizado' AS estado, gh.nombre_usuario, gh.correo_usuario, gh.telefono_usuario, gh.monto_puja, gh.fecha_puja " .
    "FROM ganadores_historial gh WHERE 1=1";

if ($fromSql !== "") {
    $query .= " AND gh.fecha_cierre >= '" . $mysqli->real_escape_string($fromSql) . "'";
}
if ($toSql !== "") {
    $query .= " AND gh.fecha_cierre <= '" . $mysqli->real_escape_string($toSql) . "'";
}
if ($categoriaFiltro > 0) {
    $query .= " AND gh.categoria_id = " . $categoriaFiltro;
}

$query .= " ORDER BY gh.fecha_cierre DESC, gh.id DESC";

$rows = [];
$result = $mysqli->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
}

$headers = ["ID", "Producto"];
if ($hasFin) {
    $headers[] = "Fecha fin";
}
$headers = array_merge($headers, [
    "Estado",
    "Ganador",
    "Correo",
    "Telefono",
    "Monto ganador (MXN)",
    "Fecha puja"
]);

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
    $filename = "ganadores-" . date("Ymd") . ".csv";
    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");

    $output = fopen("php://output", "w");
    fwrite($output, "\xEF\xBB\xBF");
    $delimiter = ";";
    writeCsvRow($output, $headers, $delimiter);

    foreach ($rows as $row) {
        $line = [
            $row["id"] ?? "",
            $row["nombre"] ?? ""
        ];
        if ($hasFin) {
            $line[] = $row["fecha_fin"] ?? "";
        }
        $line[] = $row["estado"] ?? "";
        $line[] = $row["nombre_usuario"] ?? "";
        $line[] = $row["correo_usuario"] ?? "";
        $line[] = $row["telefono_usuario"] ?? "";
        $line[] = $row["monto_puja"] ?? "";
        $line[] = $row["fecha_puja"] ?? "";
        writeCsvRow($output, $line, $delimiter);
    }

    fclose($output);
    exit;
}
$isExcel = $format === "excel";
if ($isExcel) {
    $filename = "ganadores-" . date("Ymd") . ".xls";
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    header("Cache-Control: max-age=0");
}

$generatedAt = date("Y-m-d H:i");

function display_value($value, $placeholder = "-")
{
    $text = trim((string) ($value ?? ""));
    if ($text === "") {
        return $placeholder;
    }

    return $text;
}

function display_money($value)
{
    if ($value === null || $value === "") {
        return "-";
    }

    return number_format((float) $value, 2);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Exportar ganadores</title>
    <style>
        :root {
            color-scheme: light;
        }
        body {
            margin: 24px;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            color: #1f1f1f;
        }
        header {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 16px;
        }
        h1 {
            font-size: 1.4rem;
            margin: 0;
        }
        .meta {
            color: #555;
            font-size: 0.9rem;
        }
        .hint {
            margin: 6px 0 16px;
            color: #555;
            font-size: 0.9rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        th, td {
            border: 1px solid #c9c9c9;
            padding: 8px 10px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #f1f1f1;
        }
        @page {
            size: landscape;
            margin: 12mm;
        }
        @media print {
            body {
                margin: 12mm;
            }
        }
    </style>
</head>
<body>
    <header>
        <h1>Ganadores</h1>
        <div class="meta">Generado: <?php echo htmlspecialchars($generatedAt); ?></div>
    </header>
    <?php if (!$isExcel) { ?>
        <script>
            window.addEventListener("load", function () {
                window.print();
            });
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
                    <td colspan="<?php echo count($headers); ?>">No hay subastas finalizadas.</td>
                </tr>
            <?php } else { ?>
                <?php foreach ($rows as $row) { ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string) ($row["id"] ?? "")); ?></td>
                        <td><?php echo htmlspecialchars((string) ($row["nombre"] ?? "")); ?></td>
                        <?php if ($hasFin) { ?>
                            <td><?php echo htmlspecialchars((string) ($row["fecha_fin"] ?? "")); ?></td>
                        <?php } ?>
                        <td><?php echo htmlspecialchars((string) ($row["estado"] ?? "")); ?></td>
                        <td><?php echo htmlspecialchars(display_value($row["nombre_usuario"] ?? "")); ?></td>
                        <td><?php echo htmlspecialchars(display_value($row["correo_usuario"] ?? "")); ?></td>
                        <td><?php echo htmlspecialchars(display_value($row["telefono_usuario"] ?? "")); ?></td>
                        <td><?php echo htmlspecialchars(display_money($row["monto_puja"] ?? "")); ?></td>
                        <td><?php echo htmlspecialchars((string) ($row["fecha_puja"] ?? "")); ?></td>
                    </tr>
                <?php } ?>
            <?php } ?>
        </tbody>
    </table>
</body>
</html>
