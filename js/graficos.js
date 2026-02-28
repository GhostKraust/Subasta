const $ = (id) => document.getElementById(id);

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

function renderCharts(data, lineStyle) {
  if (!data) {
    return;
  }

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
      label: {
        color: "#3a332e",
        fontSize: 12,
        formatter: (params) => `${params.name}: ${params.value}`
      },
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

function updateStats(data) {
  const bidderName = $("top-bidder-name");
  const bidderTotal = $("top-bidder-total");
  const bidderCount = $("top-bidder-count");
  const productName = $("top-product-name");
  const productCount = $("top-product-count");

  if (bidderName) {
    bidderName.textContent = data.topBidder.name || "-";
  }
  if (bidderTotal) {
    bidderTotal.textContent = "$" + Number(data.topBidder.total || 0).toLocaleString();
  }
  if (bidderCount) {
    bidderCount.textContent = (data.topBidder.count || 0) + " pujas";
  }
  if (productName) {
    productName.textContent = data.topProduct.name || "-";
  }
  if (productCount) {
    productCount.textContent = (data.topProduct.count || 0) + " pujas";
  }
}

async function fetchData(rangeKey, fromDate, toDate) {
  const params = new URLSearchParams({ data: "1", range: rangeKey });
  if (fromDate) {
    params.set("from", fromDate);
  }
  if (toDate) {
    params.set("to", toDate);
  }

  const response = await fetch(`graficos.php?${params.toString()}`);
  if (!response.ok) {
    throw new Error("No se pudo cargar la data.");
  }

  return response.json();
}

function initFilters() {
  const range = $("filter-range");
  const style = $("filter-line-style");
  const from = $("filter-from");
  const to = $("filter-to");

  const update = async () => {
    try {
      const data = await fetchData(
        range.value,
        from && from.value ? from.value : "",
        to && to.value ? to.value : ""
      );
      if (from && data.from) {
        from.value = data.from;
      }
      if (to && data.to) {
        to.value = data.to;
      }
      renderCharts(data, style.value);
      updateStats(data);
    } catch (error) {
      console.error(error);
    }
  };

  range.addEventListener("change", update);
  style.addEventListener("change", update);
  if (from) {
    from.addEventListener("change", update);
  }
  if (to) {
    to.addEventListener("change", update);
  }

  update();
}

window.addEventListener("resize", () => {
  Object.values(charts).forEach((c) => c.resize());
});

initFilters();