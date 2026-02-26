<?php
// Placeholder for future data loading.
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
  <link rel="stylesheet" href="css/graficos.css">.
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
      </div>
    </header>

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
    </section>
  </div>

  <script src="js/echarts.min.js"></script>
  <script src="js/graficos.js"></script>
</body>
</html>
