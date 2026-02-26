const $ = (id) => document.getElementById(id);

const datasets = {
  "7d": {
    hours: ["10","12","14","16","18","20","22"],
    bids: [12, 18, 15, 22, 19, 28, 24],
    topProducts: [
      { name: "Silla", value: 42 },
      { name: "Mesa", value: 36 },
      { name: "Lampara", value: 28 },
      { name: "Cuadro", value: 21 }
    ],
    states: [
      { name: "Activas", value: 52 },
      { name: "Finalizadas", value: 31 },
      { name: "Pausadas", value: 17 }
    ],
    radar: { labels: ["Arte","Muebles","Tech","Moda","Coleccion"], values: [62, 50, 75, 48, 58] }
  },
  "30d": {
    hours: ["10","12","14","16","18","20","22"],
    bids: [24, 30, 28, 35, 31, 40, 36],
    topProducts: [
      { name: "Reloj", value: 68 },
      { name: "Camara", value: 52 },
      { name: "Vinilo", value: 44 },
      { name: "Bicicleta", value: 37 }
    ],
    states: [
      { name: "Activas", value: 61 },
      { name: "Finalizadas", value: 28 },
      { name: "Pausadas", value: 11 }
    ],
    radar: { labels: ["Arte","Muebles","Tech","Moda","Coleccion"], values: [70, 58, 82, 55, 64] }
  },
  "90d": {
    hours: ["10","12","14","16","18","20","22"],
    bids: [40, 46, 44, 52, 49, 58, 54],
    topProducts: [
      { name: "Consola", value: 90 },
      { name: "Libro", value: 74 },
      { name: "Joyeria", value: 63 },
      { name: "Escultura", value: 55 }
    ],
    states: [
      { name: "Activas", value: 58 },
      { name: "Finalizadas", value: 35 },
      { name: "Pausadas", value: 7 }
    ],
    radar: { labels: ["Arte","Muebles","Tech","Moda","Coleccion"], values: [78, 64, 88, 60, 72] }
  }
};

const baseText = { fontFamily: "Space Grotesk, sans-serif", fontSize: 12, color: "#2a2420" };
const grid = { left: 36, right: 16, top: 24, bottom: 28 };

const charts = {
  line: echarts.init($("chart-line")),
  bar: echarts.init($("chart-bar")),
  pie: echarts.init($("chart-pie")),
  radar: echarts.init($("chart-radar"))
};

function lineSeries(style, data) {
  const common = {
    type: "line",
    data,
    smooth: true,
    symbol: "circle",
    symbolSize: 6,
    lineStyle: { width: 2 }
  };

  if (style === "area") {
    common.areaStyle = { opacity: 0.12 };
  } else if (style === "step") {
    common.step = "middle";
  }

  return common;
}

function renderCharts(rangeKey, lineStyle) {
  const data = datasets[rangeKey];

  charts.line.setOption({
    textStyle: baseText,
    grid,
    xAxis: {
      type: "category",
      data: data.hours,
      boundaryGap: false,
      axisLine: { lineStyle: { color: "#e2dad3" } },
      axisTick: { show: false }
    },
    yAxis: {
      type: "value",
      axisLine: { show: false },
      splitLine: { lineStyle: { color: "#efe7e0" } }
    },
    series: [lineSeries(lineStyle, data.bids)]
  });

  charts.bar.setOption({
    textStyle: baseText,
    grid,
    xAxis: {
      type: "category",
      data: data.topProducts.map((p) => p.name),
      axisLine: { lineStyle: { color: "#e2dad3" } },
      axisTick: { show: false }
    },
    yAxis: {
      type: "value",
      axisLine: { show: false },
      splitLine: { lineStyle: { color: "#efe7e0" } }
    },
    series: [{
      type: "bar",
      data: data.topProducts.map((p) => p.value),
      barWidth: 24,
      itemStyle: { borderRadius: [10, 10, 4, 4] }
    }]
  });

  charts.pie.setOption({
    textStyle: baseText,
    series: [{
      type: "pie",
      radius: ["45%", "70%"],
      data: data.states,
      label: { color: "#3a332e", fontSize: 12 },
      labelLine: { length: 10, length2: 8 }
    }]
  });

  charts.radar.setOption({
    textStyle: baseText,
    radar: {
      indicator: data.radar.labels.map((name) => ({ name, max: 100 })),
      axisLine: { lineStyle: { color: "#e2dad3" } },
      splitLine: { lineStyle: { color: "#efe7e0" } },
      splitArea: { areaStyle: { color: ["rgba(0,0,0,0)", "rgba(0,0,0,0)"] } }
    },
    series: [{
      type: "radar",
      data: [{ value: data.radar.values, name: "Rendimiento" }],
      lineStyle: { width: 2 },
      areaStyle: { opacity: 0.08 }
    }]
  });
}

function initFilters() {
  const range = $("filter-range");
  const style = $("filter-line-style");

  const update = () => renderCharts(range.value, style.value);
  range.addEventListener("change", update);
  style.addEventListener("change", update);

  update();
}

window.addEventListener("resize", () => {
  Object.values(charts).forEach((c) => c.resize());
});

initFilters();