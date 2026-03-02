<?php
require_once __DIR__ . "/auth.php";
require_admin();
require_once __DIR__ . "/../config/db.php";

if (!empty($_GET["data"])) {
  header("Content-Type: application/json; charset=utf-8");

  $rangeDays = 30;

  $fromRaw = trim($_GET["from"] ?? "");
  $toRaw = trim($_GET["to"] ?? "");
  $today = new DateTime("today");
  $fromDate = clone $today;
  $fromDate->modify("-" . ($rangeDays - 1) . " days");
  $toDate = clone $today;

  if ($fromRaw !== "") {
    $fromParsed = DateTime::createFromFormat("Y-m-d", $fromRaw);
    if ($fromParsed) {
      $fromDate = $fromParsed;
    }
  }
  if ($toRaw !== "") {
    $toParsed = DateTime::createFromFormat("Y-m-d", $toRaw);
    if ($toParsed) {
      $toDate = $toParsed;
    }
  }

  if ($fromDate > $toDate) {
    $temp = $fromDate;
    $fromDate = $toDate;
    $toDate = $temp;
  }

  $fromSql = $fromDate->format("Y-m-d 00:00:00");
  $toSql = $toDate->format("Y-m-d 23:59:59");

  $precioColumn = "predcio_inicial";
  $checkPrecio = $mysqli->query("SHOW COLUMNS FROM productos LIKE 'predcio_inicial'");
  if ($checkPrecio && $checkPrecio->num_rows === 0) {
    $checkPrecioAlt = $mysqli->query("SHOW COLUMNS FROM productos LIKE 'precio_inicial'");
    if ($checkPrecioAlt && $checkPrecioAlt->num_rows > 0) {
      $precioColumn = "precio_inicial";
    }
  }

  $hours = [];
  $bids = [];
  for ($i = 0; $i < 24; $i++) {
    $hours[] = str_pad((string) $i, 2, "0", STR_PAD_LEFT);
    $bids[] = 0;
  }

  $stmtHours = $mysqli->prepare("SELECT HOUR(fecha_puja) AS hr, COUNT(*) AS total FROM pujas WHERE fecha_puja BETWEEN ? AND ? GROUP BY hr");
  if ($stmtHours) {
    $stmtHours->bind_param("ss", $fromSql, $toSql);
    $stmtHours->execute();
    $result = $stmtHours->get_result();
    while ($row = $result->fetch_assoc()) {
      $hr = (int) ($row["hr"] ?? 0);
      if ($hr >= 0 && $hr < 24) {
        $bids[$hr] = (int) ($row["total"] ?? 0);
      }
    }
    $stmtHours->close();
  }

  $topProducts = [];
  $stmtTop = $mysqli->prepare(
    "SELECT p.id, TRIM(p.nombre) AS nombre, COUNT(pu.id) AS total " .
    "FROM productos p " .
    "LEFT JOIN pujas pu ON pu.producto_id = p.id AND pu.fecha_puja BETWEEN ? AND ? " .
    "WHERE p.nombre IS NOT NULL AND TRIM(p.nombre) <> '' " .
    "GROUP BY p.id HAVING total > 0 ORDER BY total DESC, p.id DESC LIMIT 5"
  );
  if ($stmtTop) {
    $stmtTop->bind_param("ss", $fromSql, $toSql);
    $stmtTop->execute();
    $result = $stmtTop->get_result();
    while ($row = $result->fetch_assoc()) {
      $topProducts[] = [
        "name" => $row["nombre"],
        "value" => (int) ($row["total"] ?? 0)
      ];
    }
    $stmtTop->close();
  }

  $states = [];
  $resultStates = $mysqli->query("SELECT estado, COUNT(*) AS total FROM productos GROUP BY estado");
  if ($resultStates) {
    while ($row = $resultStates->fetch_assoc()) {
      $label = ucfirst((string) ($row["estado"] ?? ""));
      $states[] = ["name" => $label, "value" => (int) ($row["total"] ?? 0)];
    }
  }

  $radarLabels = [];
  $radarValues = [];
  $stmtRadar = $mysqli->prepare(
    "SELECT c.nombre, SUM(COALESCE(mx.max_puja, mx.precio_base)) AS total " .
    "FROM categorias c " .
    "LEFT JOIN (" .
      "SELECT p.id, p.categoria_id, COALESCE(MAX(pu.monto_puja), p.$precioColumn) AS max_puja, p.$precioColumn AS precio_base " .
      "FROM productos p " .
      "LEFT JOIN pujas pu ON pu.producto_id = p.id AND pu.fecha_puja BETWEEN ? AND ? " .
      "GROUP BY p.id" .
    ") mx ON mx.categoria_id = c.id " .
    "GROUP BY c.id, c.nombre ORDER BY c.nombre"
  );
  if ($stmtRadar) {
    $stmtRadar->bind_param("ss", $fromSql, $toSql);
    $stmtRadar->execute();
    $result = $stmtRadar->get_result();
    $maxValue = 1;
    $rawTotals = [];
    while ($row = $result->fetch_assoc()) {
      $total = (float) ($row["total"] ?? 0);
      $rawTotals[] = $total;
      $radarLabels[] = $row["nombre"] ?? "Sin categoria";
      if ($total > $maxValue) {
        $maxValue = $total;
      }
    }
    foreach ($rawTotals as $total) {
      $radarValues[] = $maxValue > 0 ? round(($total / $maxValue) * 100) : 0;
    }
    $stmtRadar->close();
  }

  $topBidder = ["name" => "-", "total" => 0, "count" => 0];
  $stmtBidder = $mysqli->prepare(
    "SELECT nombre_usuario, SUM(monto_puja) AS total, COUNT(*) AS total_pujas " .
    "FROM pujas WHERE fecha_puja BETWEEN ? AND ? GROUP BY nombre_usuario " .
    "ORDER BY total DESC LIMIT 1"
  );
  if ($stmtBidder) {
    $stmtBidder->bind_param("ss", $fromSql, $toSql);
    $stmtBidder->execute();
    $result = $stmtBidder->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    if ($row) {
      $topBidder = [
        "name" => $row["nombre_usuario"] ?? "-",
        "total" => (float) ($row["total"] ?? 0),
        "count" => (int) ($row["total_pujas"] ?? 0)
      ];
    }
    $stmtBidder->close();
  }

  $topProduct = [];
  $stmtMost = $mysqli->prepare(
    "SELECT p.nombre, COUNT(pu.id) AS total " .
    "FROM productos p " .
    "LEFT JOIN pujas pu ON pu.producto_id = p.id AND pu.fecha_puja BETWEEN ? AND ? " .
    "GROUP BY p.id ORDER BY total DESC, p.id DESC LIMIT 3"
  );
  if ($stmtMost) {
    $stmtMost->bind_param("ss", $fromSql, $toSql);
    $stmtMost->execute();
    $result = $stmtMost->get_result();
    while ($row = $result->fetch_assoc()) {
      $topProduct[] = [
        "name" => $row["nombre"] ?? "-",
        "count" => (int) ($row["total"] ?? 0)
      ];
    }
    $stmtMost->close();
  }

  echo json_encode([
    "hours" => $hours,
    "bids" => $bids,
    "topProducts" => $topProducts,
    "states" => $states,
    "radar" => ["labels" => $radarLabels, "values" => $radarValues],
    "topBidder" => $topBidder,
    "topProduct" => $topProduct,
    "from" => $fromDate->format("Y-m-d"),
    "to" => $toDate->format("Y-m-d")
  ]);
  exit;
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Panel de Graficas</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/graficos.css">
  <script src="https://cdn.jsdelivr.net/npm/echarts@5/dist/echarts.min.js"></script>
</head>
<body>
  <div class="page">
    <header class="hero">
      <div>
        <p class="eyebrow">Panel de control</p>
        <h1>Graficas para subasta</h1>
        <p class="subtitle">Cambios de color rapidos y widgets listos para conectar datos reales.</p>
      </div>
      <div class="theme-switch">
        <button class="theme-btn" data-theme="sunset">Sunset</button>
        <button class="theme-btn" data-theme="ocean">Ocean</button>
        <button class="theme-btn" data-theme="mint">Mint</button>
        <a class="theme-btn" href="dashboard.php">Dashboard</a>
      </div>
    </header>

    <section class="filters">
      <label class="filter">
        Desde
        <input id="filter-from" type="date" />
      </label>

      <label class="filter">
        Hasta
        <input id="filter-to" type="date" />
      </label>
    </section>

    <section class="grid">
      <article class="card">
        <div class="card-head">
          <h2>Pujas por hora</h2>
          <span class="chip">Tiempo real</span>
        </div>
        <div id="chart-line" class="chart"></div>
      </article>

      <article class="card">
        <div class="card-head">
          <h2>Top productos</h2>
          <span class="chip">Ranking</span>
        </div>
        <div id="chart-bar" class="chart"></div>
      </article>

      <article class="card">
        <div class="card-head">
          <h2>Distribucion de estados</h2>
          <span class="chip">Actual</span>
        </div>
        <div id="chart-pie" class="chart"></div>
      </article>

      <article class="card">
        <div class="card-head">
          <h2>Rendimiento por categoria</h2>
          <span class="chip">Comparativo</span>
        </div>
        <div id="chart-radar" class="chart"></div>
      </article>

      <article class="card">
        <div class="card-head">
          <h2>Mayor postor</h2>
          <span class="chip">Periodo</span>
        </div>
        <div class="stat-block">
          <h3 id="top-bidder-name">-</h3>
          <p id="top-bidder-total">$0</p>
          <small id="top-bidder-count">0 pujas</small>
        </div>
      </article>

      <article class="card">
        <div class="card-head">
          <h2>Productos mas pujados</h2>
          <span class="chip">Periodo</span>
        </div>
        <div class="stat-block">
          <ul id="top-product-list" class="stat-list"></ul>
        </div>
      </article>
    </section>
  </div>

  <script src="../js/graficos.js?v=2"></script>
</body>

</html>



