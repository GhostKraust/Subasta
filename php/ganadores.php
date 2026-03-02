<?php
require_once __DIR__ . "/auth.php";
require_admin();
require_once __DIR__ . "/../config/db.php";

$hasFin = false;
$checkFin = $mysqli->query("SHOW COLUMNS FROM productos LIKE 'fecha_fin'");
if ($checkFin && $checkFin->num_rows > 0) {
    $hasFin = true;
}

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
    WHERE $condFinal
    ORDER BY " . ($hasFin ? "p.fecha_fin DESC, " : "") . "p.id DESC";

$ganadores = [];
$result = $mysqli->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $ganadores[] = $row;
    }
}
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
            <a class="btn ghost" href="export_ganadores.php?format=excel">Exportar Excel</a>
            <a class="btn ghost" href="export_ganadores.php?format=pdf" target="_blank" rel="noopener">Exportar PDF</a>
            <a class="btn ghost" href="dashboard.php">Volver al dashboard</a>
            <a class="btn" href="panel.php">Panel</a>
        </div>
    </header>

    <main class="dashboard">
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
