(() => {
  const initial = JSON.parse(document.querySelector("#history-data").textContent);
  const deviceId = initial.device.device_id;
  const content = document.querySelector("#history-content");
  const status = document.querySelector("#history-status");
  const error = document.querySelector("#history-error");
  const empty = document.querySelector("#history-empty");
  const area = document.querySelector("#history-chart-area");
  const summary = document.querySelector("#history-summary");
  const legend = document.querySelector("#history-legend");
  const refresh = document.querySelector("#history-refresh");
  const canvas = document.querySelector("#history-chart");
  const colors = ["success", "warning", "danger", "secondary"].map((name) => {
    const badge = document.querySelector(`#history-legend .badge-${name}`);
    return getComputedStyle(badge).backgroundColor;
  });
  const primary = getComputedStyle(refresh).color;
  const dateFormat = new Intl.DateTimeFormat("en-GB", {
    timeZone: initial.timezone,
    day: "2-digit", month: "short", hour: "2-digit", minute: "2-digit", second: "2-digit",
  });
  const tickFormat = new Intl.DateTimeFormat("en-GB", {
    timeZone: initial.timezone, hour: "2-digit", minute: "2-digit",
  });
  let chart;
  let timer;
  let controller;
  let busy = false;
  let stopped = false;

  function render(data) {
    if (data.device.device_id !== deviceId || !Array.isArray(data.points)) {
      throw new Error("Invalid history response");
    }
    const points = data.points;
    area.hidden = points.length === 0;
    empty.hidden = points.length !== 0;
    legend.hidden = points.length === 0;
    if (points.length) {
      const firstTime = points[0].x;
      const lastTime = points[points.length - 1].x;
      const padding = firstTime === lastTime ? 30000 : 0;
      const dataset = {
        label: "Water level (cm)",
        data: points,
        parsing: false,
        borderColor: primary,
        backgroundColor: primary,
        pointBackgroundColor: points.map((point) => colors[point.alarm_status ?? 3] ?? colors[3]),
        pointBorderColor: points.map((point) => colors[point.alarm_status ?? 3] ?? colors[3]),
        pointRadius: points.length > 100 ? 1 : 3,
        pointHoverRadius: 5,
        borderWidth: 2,
        tension: 0,
        showLine: true,
        spanGaps: false,
      };
      if (chart) {
        chart.data.datasets = [dataset];
        chart.options.scales.x.min = firstTime - padding;
        chart.options.scales.x.max = lastTime + padding;
        chart.resize();
        chart.update("none");
      } else {
        chart = new Chart(canvas, {
          type: "line",
          data: { datasets: [dataset] },
          options: {
            responsive: true,
            aspectRatio: 1.3,
            animation: false,
            scales: {
              x: {
                type: "linear",
                min: firstTime - padding,
                max: lastTime + padding,
                title: { display: true, text: `Time (${data.timezone})` },
                ticks: { maxTicksLimit: 6, callback: (value) => tickFormat.format(new Date(value)) },
              },
              y: { beginAtZero: true, title: { display: true, text: "Water level (cm)" } },
            },
            plugins: {
              legend: { display: false },
              tooltip: {
                callbacks: {
                  title: (items) => dateFormat.format(new Date(items[0].parsed.x)),
                  label: (item) => {
                    const point = item.raw;
                    const label = point.alarm_status === null ? "Unclassified" : `Siaga ${point.alarm_status + 1}`;
                    return `${point.y.toFixed(2)} cm · ${label}`;
                  },
                },
              },
            },
          },
        });
      }
      const levels = points.map((point) => point.y);
      summary.textContent = `${points.length} readings · ${dateFormat.format(new Date(points[0].x))} – ${dateFormat.format(new Date(points[points.length - 1].x))} · Min ${Math.min(...levels).toFixed(2)} cm · Max ${Math.max(...levels).toFixed(2)} cm`;
    } else {
      if (chart) chart.data.datasets = [];
      summary.textContent = "No historical measurements available.";
    }
    if (typeof data.html === "string") document.querySelector("#history-device").innerHTML = data.html;
  }

  async function refreshHistory() {
    if (busy || stopped) return;
    clearTimeout(timer);
    busy = true;
    refresh.disabled = true;
    content.setAttribute("aria-busy", "true");
    status.textContent = "Updating water level history…";
    controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 15000);
    try {
      const response = await fetch(`/api/device-history.php?${new URLSearchParams({ device_id: deviceId })}`, {
        cache: "no-store", signal: controller.signal,
      });
      if (!response.ok) throw new Error("History request failed");
      const data = await response.json();
      if (data.status !== "success" || stopped) throw new Error("History unavailable");
      render(data);
      error.hidden = true;
      status.textContent = "Updated " + new Date().toLocaleTimeString("en-GB");
    } catch (_) {
      if (!stopped) {
        error.textContent = "Unable to refresh history. The last loaded data is retained. Try again.";
        error.hidden = false;
        status.textContent = "Connection lost · retrying";
      }
    } finally {
      clearTimeout(timeout);
      busy = false;
      refresh.disabled = false;
      content.setAttribute("aria-busy", "false");
      if (!stopped) timer = setTimeout(refreshHistory, 5000);
    }
  }

  refresh.addEventListener("click", () => {
    if (typeof Chart === "undefined") window.location.reload();
    else refreshHistory();
  });
  if (typeof Chart === "undefined") {
    error.textContent = "The chart could not be loaded. Reload this page to try again.";
    error.hidden = false;
    status.textContent = "Chart unavailable";
    refresh.textContent = "Reload page";
    content.setAttribute("aria-busy", "false");
    return;
  }
  render(initial);
  content.setAttribute("aria-busy", "false");
  status.textContent = "Refreshes every 5 seconds";
  timer = setTimeout(refreshHistory, 5000);
  window.addEventListener("pagehide", () => {
    stopped = true;
    clearTimeout(timer);
    controller?.abort();
    chart?.destroy();
  });
  window.addEventListener("pageshow", (event) => {
    if (event.persisted) window.location.reload();
  });
})();
