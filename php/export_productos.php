<?php
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/../config/db.php";

$format = strtolower(trim($_GET["format"] ?? "csv"));
if (!in_array($format, ["csv", "pdf", "excel"], true)) {
    http_response_code(400);
    exit("Formato no valido.");
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

$selectIncremento = $hasIncremento ? ", p.incremento_minimo AS incremento" : ", NULL AS incremento";
$selectInicio = $hasInicio ? ", p.fecha_inicio" : ", NULL AS fecha_inicio";
$selectFin = $hasFin ? ", p.fecha_fin" : ", NULL AS fecha_fin";

$query = "SELECT p.id, p.nombre, c.nombre AS categoria, p.$precioColumn AS precio$selectIncremento$selectInicio$selectFin, p.estado, mx.max_puja, w.nombre_usuario, w.correo_usuario, w.telefono_usuario, w.monto_puja AS ganador_monto, w.fecha_puja AS ganador_fecha " .
    "FROM productos p " .
    "LEFT JOIN categorias c ON p.categoria_id = c.id " .
    "LEFT JOIN (SELECT producto_id, MAX(monto_puja) AS max_puja FROM pujas GROUP BY producto_id) mx ON mx.producto_id = p.id " .
    "LEFT JOIN (" .
        "SELECT pu1.* " .
        "FROM pujas pu1 " .
        "INNER JOIN (" .
            "SELECT producto_id, MAX(monto_puja) AS max_monto " .
            "FROM pujas GROUP BY producto_id" .
        ") mx2 ON pu1.producto_id = mx2.producto_id AND pu1.monto_puja = mx2.max_monto " .
        "INNER JOIN (" .
            "SELECT producto_id, MAX(fecha_puja) AS max_fecha " .
            "FROM pujas GROUP BY producto_id" .
        ") mf ON pu1.producto_id = mf.producto_id AND pu1.fecha_puja = mf.max_fecha" .
    ") w ON w.producto_id = p.id " .
    "ORDER BY p.id DESC";

$rows = [];
$result = $mysqli->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
}

$headers = [
    "ID",
    "Producto",
    "Categoria",
    "Precio inicial (MXN)"
];
if ($hasIncremento) {
    $headers[] = "Incremento (MXN)";
}
if ($hasInicio) {
    $headers[] = "Fecha inicio";
}
if ($hasFin) {
    $headers[] = "Fecha fin";
}
$headers = array_merge($headers, [
    "Estado",
    "Max puja (MXN)",
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
    $filename = "productos-" . date("Ymd") . ".csv";
    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");

    $output = fopen("php://output", "w");
    fwrite($output, "\xEF\xBB\xBF");
    $delimiter = ";";
    writeCsvRow($output, $headers, $delimiter);

    foreach ($rows as $row) {
        $line = [
            $row["id"] ?? "",
            $row["nombre"] ?? "",
            $row["categoria"] ?? "",
            $row["precio"] ?? ""
        ];
        if ($hasIncremento) {
            $line[] = $row["incremento"] ?? "";
        }
        if ($hasInicio) {
            $line[] = $row["fecha_inicio"] ?? "";
        }
        if ($hasFin) {
            $line[] = $row["fecha_fin"] ?? "";
        }
        $line[] = $row["estado"] ?? "";
        $line[] = $row["max_puja"] ?? "";
        $line[] = $row["nombre_usuario"] ?? "";
        $line[] = $row["correo_usuario"] ?? "";
        $line[] = $row["telefono_usuario"] ?? "";
        $line[] = $row["ganador_monto"] ?? "";
        $line[] = $row["ganador_fecha"] ?? "";
        writeCsvRow($output, $line, $delimiter);
    }

    fclose($output);
    exit;
}
$isExcel = $format === "excel";
if ($isExcel) {
    $filename = "productos-" . date("Ymd") . ".xls";
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
    <title>Exportar productos</title>
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
        @page {
            size: landscape;
            margin: 12mm;
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
        @media print {
            body {
                margin: 12mm;
            }
        }
    </style>
</head>
<body>
    <header>
        <h1>Productos</h1>
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
                    <td colspan="<?php echo count($headers); ?>">No hay productos registrados.</td>
                </tr>
            <?php } else { ?>
                <?php foreach ($rows as $row) { ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string) ($row["id"] ?? "")); ?></td>
                        <td><?php echo htmlspecialchars((string) ($row["nombre"] ?? "")); ?></td>
                        <td><?php echo htmlspecialchars((string) ($row["categoria"] ?? "")); ?></td>
                        <td><?php echo htmlspecialchars((string) ($row["precio"] ?? "")); ?></td>
                        <?php if ($hasIncremento) { ?>
                            <td><?php echo htmlspecialchars((string) ($row["incremento"] ?? "")); ?></td>
                        <?php } ?>
                        <?php if ($hasInicio) { ?>
                            <td><?php echo htmlspecialchars((string) ($row["fecha_inicio"] ?? "")); ?></td>
                        <?php } ?>
                        <?php if ($hasFin) { ?>
                            <td><?php echo htmlspecialchars((string) ($row["fecha_fin"] ?? "")); ?></td>
                        <?php } ?>
                        <td><?php echo htmlspecialchars((string) ($row["estado"] ?? "")); ?></td>
                        <td><?php echo htmlspecialchars((string) ($row["max_puja"] ?? "")); ?></td>
                        <td><?php echo htmlspecialchars((string) ($row["nombre_usuario"] ?? "")); ?></td>
                        <td><?php echo htmlspecialchars((string) ($row["correo_usuario"] ?? "")); ?></td>
                        <td><?php echo htmlspecialchars((string) ($row["telefono_usuario"] ?? "")); ?></td>
                        <td><?php echo htmlspecialchars((string) ($row["ganador_monto"] ?? "")); ?></td>
                        <td><?php echo htmlspecialchars((string) ($row["ganador_fecha"] ?? "")); ?></td>
                    </tr>
                <?php } ?>
            <?php } ?>
        </tbody>
    </table>
</body>
</html>
