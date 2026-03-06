const $ = (id) => document.getElementById(id);

const baseText = { fontFamily: "Space Grotesk, sans-serif", fontSize: 12, color: "#2a2420" };
const grid = { left: 36, right: 16, top: 24, bottom: 28 };
const pink = {
  primary: "#f06bb9",
  soft: "#f7a8d7",
  pale: "#fde1f0",
  line: "#f3c2dd"
};

const charts = {
  line: echarts.init($("chart-line")),
  bar: echarts.init($("chart-bar")),
  pie: echarts.init($("chart-pie")),
  radar: echarts.init($("chart-radar"))
};

function lineSeries(data) {
  return {
    type: "line",
    data,
    smooth: true,
    symbol: "circle",
    symbolSize: 6,
    lineStyle: { width: 2, color: pink.primary },
    itemStyle: { color: pink.primary },
    areaStyle: { color: pink.pale, opacity: 0.6 }
  };
}

function renderCharts(data) {
  if (!data) {
    return;
  }

  charts.line.setOption({
    textStyle: baseText,
    grid,
    color: [pink.primary],
    xAxis: {
      type: "category",
      data: data.hours,
      boundaryGap: false,
      axisLine: { lineStyle: { color: pink.line } },
      axisTick: { show: false }
    },
    yAxis: {
      type: "value",
      axisLine: { show: false },
      splitLine: { lineStyle: { color: pink.line } }
    },
    series: [lineSeries(data.bids)]
  });

  charts.bar.setOption({
    textStyle: baseText,
    grid: { left: 36, right: 16, top: 24, bottom: 56 },
    color: [pink.primary],
    xAxis: {
      type: "category",
      data: data.topProducts.map((p) => p.name),
      axisLine: { lineStyle: { color: pink.line } },
      axisTick: { show: false },
      axisLabel: {
        interval: 0,
        rotate: 20,
        formatter: (value) => {
          if (!value) {
            return "";
          }
          return value.length > 14 ? value.slice(0, 14) + "..." : value;
        }
      }
    },
    yAxis: {
      type: "value",
      axisLine: { show: false },
      splitLine: { lineStyle: { color: pink.line } }
    },
    series: [{
      type: "bar",
      data: data.topProducts.map((p) => p.value),
      barWidth: 24,
      itemStyle: { borderRadius: [10, 10, 4, 4], color: pink.primary }
    }]
  });

  charts.pie.setOption({
    textStyle: baseText,
    color: [pink.primary, pink.soft, "#f7c6e1", "#ffd6e9"],
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
      axisLine: { lineStyle: { color: pink.line } },
      splitLine: { lineStyle: { color: pink.line } },
      splitArea: { areaStyle: { color: ["rgba(0,0,0,0)", "rgba(0,0,0,0)"] } }
    },
    series: [{
      type: "radar",
      data: [{ value: data.radar.values, name: "Rendimiento" }],
      lineStyle: { width: 2, color: pink.primary },
      itemStyle: { color: pink.primary },
      areaStyle: { color: pink.pale, opacity: 0.5 }
    }]
  });
}

function updateStats(data) {
  const bidderName = $("top-bidder-name");
  const bidderTotal = $("top-bidder-total");
  const bidderCount = $("top-bidder-count");
  const productList = $("top-product-list");

  if (bidderName) {
    bidderName.textContent = data.topBidder.name || "-";
  }
  if (bidderTotal) {
    bidderTotal.textContent = "$" + Number(data.topBidder.total || 0).toLocaleString();
  }
  if (bidderCount) {
    bidderCount.textContent = (data.topBidder.count || 0) + " pujas";
  }
  if (productList) {
    productList.innerHTML = "";
    const items = Array.isArray(data.topProduct) ? data.topProduct : [];
    if (items.length === 0) {
      const li = document.createElement("li");
      li.textContent = "-";
      productList.appendChild(li);
    } else {
      items.forEach((item) => {
        const li = document.createElement("li");
        const name = item && item.name ? item.name : "-";
        const count = item && item.count ? item.count : 0;
        li.textContent = name + " - " + count + " pujas";
        productList.appendChild(li);
      });
    }
  }
}

async function fetchData(fromDate, toDate) {
  const params = new URLSearchParams({ data: "1" });
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
  const from = $("filter-from");
  const to = $("filter-to");

  const update = async () => {
    try {
      const data = await fetchData(
        from && from.value ? from.value : "",
        to && to.value ? to.value : ""
      );
      if (from && data.from) {
        from.value = data.from;
      }
      if (to && data.to) {
        to.value = data.to;
      }
      renderCharts(data);
      updateStats(data);
    } catch (error) {
      console.error(error);
    }
  };
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