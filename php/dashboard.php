<?php
require_once __DIR__ . "/auth.php";
require_admin();
require_once __DIR__ . "/../config/db.php";

$precioColumn = "predcio_inicial";
$checkPrecio = $mysqli->query("SHOW COLUMNS FROM productos LIKE 'predcio_inicial'");
if ($checkPrecio && $checkPrecio->num_rows === 0) {
    $checkPrecioAlt = $mysqli->query("SHOW COLUMNS FROM productos LIKE 'precio_inicial'");
    if ($checkPrecioAlt && $checkPrecioAlt->num_rows > 0) {
        $precioColumn = "precio_inicial";
    }
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

$hasOrigen = false;
$checkOrigen = $mysqli->query("SHOW COLUMNS FROM pujas LIKE 'origen'");
if ($checkOrigen && $checkOrigen->num_rows > 0) {
    $hasOrigen = true;
}

$categorias = [];
$maxCategoria = 1;
$resultCategorias = $mysqli->query("SELECT c.id, c.nombre, COUNT(p.id) AS total FROM categorias c LEFT JOIN productos p ON p.categoria_id = c.id GROUP BY c.id, c.nombre ORDER BY c.nombre ASC");
if ($resultCategorias) {
    while ($row = $resultCategorias->fetch_assoc()) {
        $count = (int) $row["total"];
        if ($count > $maxCategoria) {
            $maxCategoria = $count;
        }
        $categorias[] = $row;
    }
}

$totalPujas = 0;
$resultTotalPujas = $mysqli->query("SELECT COUNT(*) AS total FROM pujas");
if ($resultTotalPujas) {
    $row = $resultTotalPujas->fetch_assoc();
    $totalPujas = (int) ($row["total"] ?? 0);
}

$topProductos = [];
$queryTop = "SELECT p.nombre, COALESCE(MAX(pu.monto_puja), p.$precioColumn) AS monto_final FROM productos p LEFT JOIN pujas pu ON pu.producto_id = p.id GROUP BY p.id ORDER BY monto_final DESC LIMIT 5";
$resultTop = $mysqli->query($queryTop);
if ($resultTop) {
    while ($row = $resultTop->fetch_assoc()) {
        $topProductos[] = $row;
    }
}

$estadoActivas = 0;
$estadoFinalizadas = 0;
$estadoProximas = 0;
if ($hasInicio || $hasFin) {
    $resultActivas = $mysqli->query("SELECT COUNT(*) AS total FROM productos WHERE estado = 'activo' AND (fecha_inicio IS NULL OR fecha_inicio <= NOW()) AND (fecha_fin IS NULL OR fecha_fin >= NOW())");
    $resultFinal = $mysqli->query("SELECT COUNT(*) AS total FROM productos WHERE estado = 'finalizado' OR (fecha_fin IS NOT NULL AND fecha_fin < NOW())");
    $resultProx = $mysqli->query("SELECT COUNT(*) AS total FROM productos WHERE estado = 'activo' AND fecha_inicio IS NOT NULL AND fecha_inicio > NOW()");
    if ($resultActivas) {
        $row = $resultActivas->fetch_assoc();
        $estadoActivas = (int) ($row["total"] ?? 0);
    }
    if ($resultFinal) {
        $row = $resultFinal->fetch_assoc();
        $estadoFinalizadas = (int) ($row["total"] ?? 0);
    }
    if ($resultProx) {
        $row = $resultProx->fetch_assoc();
        $estadoProximas = (int) ($row["total"] ?? 0);
    }
} else {
    $resultActivas = $mysqli->query("SELECT COUNT(*) AS total FROM productos WHERE estado = 'activo'");
    $resultFinal = $mysqli->query("SELECT COUNT(*) AS total FROM productos WHERE estado = 'finalizado'");
    if ($resultActivas) {
        $row = $resultActivas->fetch_assoc();
        $estadoActivas = (int) ($row["total"] ?? 0);
    }
    if ($resultFinal) {
        $row = $resultFinal->fetch_assoc();
        $estadoFinalizadas = (int) ($row["total"] ?? 0);
    }
}

$ultimasPujas = [];
$selectOrigen = $hasOrigen ? ", pu.origen" : "";
$resultPujas = $mysqli->query("SELECT p.nombre AS producto, pu.nombre_usuario, pu.monto_puja, pu.fecha_puja$selectOrigen FROM pujas pu INNER JOIN productos p ON p.id = pu.producto_id ORDER BY pu.fecha_puja DESC LIMIT 6");
if ($resultPujas) {
    while ($row = $resultPujas->fetch_assoc()) {
        $ultimasPujas[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Administracion - Dashboard</title>
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
                <div class="brand-tag">Resumen de subastas</div>
            </div>
        </div>
        <div class="dash-actions">
            <a class="btn btn-compact ghost" href="graficos.php">Graficas</a>
            <a class="btn btn-compact ghost" href="subasta.php">Volver a subasta</a>
            <a class="btn btn-compact ghost" href="panel.php">Nuevo producto</a>
            <a class="btn btn-compact" href="logout.php">Cerrar sesion</a>
        </div>
    </header>

    <main class="dashboard">
        <section class="dash-grid">
            <article class="card">
                <div class="card-title">Productos por categoria</div>
                <div class="chart">
                    <?php if (count($categorias) === 0) { ?>
                        <div class="note">No hay categorias registradas.</div>
                    <?php } else { ?>
                        <?php foreach ($categorias as $categoria) { ?>
                            <?php
                                $count = (int) $categoria["total"];
                                $percent = $maxCategoria > 0 ? round(($count / $maxCategoria) * 100) : 0;
                            ?>
                            <div class="bar-row">
                                <span><?php echo htmlspecialchars($categoria["nombre"]); ?></span>
                                <div class="bar-track"><div class="bar" style="width: <?php echo $percent; ?>%"></div></div>
                                <strong><?php echo $count; ?></strong>
                            </div>
                        <?php } ?>
                    <?php } ?>
                </div>
            </article>

            <article class="card">
                <div class="card-title">Pujas registradas</div>
                <div class="pill-row">
                    <span class="pill">Total: <?php echo $totalPujas; ?></span>
                </div>
                <div class="note">Ver ganadores de subastas cerradas.</div>
                <a class="btn ghost" href="ganadores.php">Ver ganadores</a>
            </article>

            <article class="card">
                <div class="card-title">Top productos por monto final</div>
                <ol class="ranked">
                    <?php if (count($topProductos) === 0) { ?>
                        <li><span>Sin datos</span><strong>$0</strong></li>
                    <?php } else { ?>
                        <?php foreach ($topProductos as $top) { ?>
                            <li>
                                <span><?php echo htmlspecialchars($top["nombre"] ?? ""); ?></span>
                                <strong>$<?php echo number_format((float) ($top["monto_final"] ?? 0), 2); ?></strong>
                            </li>
                        <?php } ?>
                    <?php } ?>
                </ol>
            </article>
        </section>

        <section class="dash-grid">
            <article class="card">
                <div class="card-title">Estado de subastas</div>
                <div class="pill-row">
                    <span class="pill">Activas: <?php echo $estadoActivas; ?></span>
                    <span class="pill">Finalizadas: <?php echo $estadoFinalizadas; ?></span>
                    <?php if ($estadoProximas > 0) { ?>
                        <span class="pill">Proximas: <?php echo $estadoProximas; ?></span>
                    <?php } ?>
                </div>
                <div class="note">Puedes editar productos antes de publicar.</div>
            </article>

            <article class="card">
                <div class="card-title">Ultimas pujas</div>
                <div class="table">
                    <div class="row head">
                        <span>Producto</span>
                        <span>Monto</span>
                        <span><?php echo $hasOrigen ? "Origen" : "Fecha"; ?></span>
                    </div>
                    <?php if (count($ultimasPujas) === 0) { ?>
                        <div class="row">
                            <span>Sin pujas</span>
                            <span>$0</span>
                            <span>-</span>
                        </div>
                    <?php } else { ?>
                        <?php foreach ($ultimasPujas as $puja) { ?>
                            <div class="row">
                                <span><?php echo htmlspecialchars($puja["producto"] ?? ""); ?></span>
                                <span>$<?php echo number_format((float) ($puja["monto_puja"] ?? 0), 2); ?></span>
                                <span>
                                    <?php if ($hasOrigen) { ?>
                                        <?php echo htmlspecialchars($puja["origen"] ?? "-"); ?>
                                    <?php } else { ?>
                                        <?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($puja["fecha_puja"] ?? ""))); ?>
                                    <?php } ?>
                                </span>
                            </div>
                        <?php } ?>
                    <?php } ?>
                </div>
            </article>
        </section>
    </main>
</body>
</html>
