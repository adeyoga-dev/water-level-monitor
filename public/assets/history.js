(() => {
  const initial = JSON.parse(
    document.querySelector("#history-data").textContent,
  );
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
  const form = document.querySelector("#history-filters");
  const apply = document.querySelector("#history-apply");
  const reset = document.querySelector("#history-reset");
  const filterState = document.querySelector("#history-filter-state");
  const note = document.querySelector("#history-note");
  const colors = ["success", "warning", "danger", "secondary"].map(
    (name) =>
      getComputedStyle(document.querySelector(`#history-legend .badge-${name}`))
        .backgroundColor,
  );
  const primary = getComputedStyle(document.documentElement)
    .getPropertyValue("--accent-blue-deep")
    .trim();
  const labels = {
    minute: "Per menit",
    hour: "Per jam",
    day: "Harian",
    month: "Bulanan",
    year: "Tahunan",
  };
  const fullDate = new Intl.DateTimeFormat("id-ID", {
    timeZone: initial.timezone,
    year: "numeric",
    month: "short",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit",
    timeZoneName: "short",
  });
  let chart;
  let timer;
  let controller;
  let busy = false;
  let stopped = false;
  let revision = 0;
  let appliedFilters = initial.filters;

  function readFilters() {
    return {
      from: form.elements.from.value,
      to: form.elements.to.value,
      group_by: form.elements.group_by.value,
    };
  }

  function dirty() {
    return JSON.stringify(readFilters()) !== JSON.stringify(appliedFilters);
  }

  function controls() {
    refresh.disabled = busy || dirty();
    apply.disabled = busy;
    reset.disabled = busy;
    content.setAttribute("aria-busy", String(busy));
    filterState.textContent = dirty()
      ? "Filter belum diterapkan. Pembaruan otomatis dijeda; terapkan filter untuk memperbarui grafik."
      : "";
  }

  function schedule() {
    clearTimeout(timer);
    if (!stopped && !dirty())
      timer = setTimeout(() => loadHistory(appliedFilters), 5000);
  }

  function render(data) {
    if (data.device.device_id !== deviceId || !Array.isArray(data.points))
      throw new Error("Invalid history response");
    const grouped = data.aggregation !== "raw";
    const points = data.points;
    const populated = points.filter((point) => point.y !== null);
    area.hidden = populated.length === 0;
    empty.hidden = populated.length !== 0;
    legend.hidden = grouped || populated.length === 0;
    const unit = data.aggregation;
    const tickOptions =
      unit === "year"
        ? { year: "numeric" }
        : unit === "month"
          ? { month: "short", year: "numeric" }
          : unit === "day"
            ? { day: "2-digit", month: "short" }
            : {
                hour: "2-digit",
                minute: "2-digit",
                ...(data.filters?.from !== data.filters?.to
                  ? { day: "2-digit", month: "short" }
                  : {}),
              };
    const tickFormat = new Intl.DateTimeFormat("id-ID", {
      timeZone: data.timezone,
      ...tickOptions,
    });
    const tooltipFormat = new Intl.DateTimeFormat("id-ID", {
      timeZone: data.timezone,
      ...tickOptions,
      ...(unit === "day" ? { year: "numeric" } : {}),
    });
    const radius = points.length <= 31 ? 3 : points.length <= 62 ? 2 : 0;
    const base = {
      parsing: false,
      pointRadius: (context) => {
        const index = context.dataIndex;
        if ((points[index]?.y ?? null) === null) return 0;
        const isolated =
          (points[index - 1]?.y ?? null) === null &&
          (points[index + 1]?.y ?? null) === null;
        return isolated ? 3 : radius;
      },
      pointHoverRadius: 6,
      pointHitRadius: 12,
      borderWidth: 2,
      tension: 0,
      spanGaps: false,
    };
    const datasets = grouped
      ? [
          {
            label: "Rata-rata (cm)",
            borderColor: primary,
            backgroundColor: primary,
            data: points,
          },
          {
            label: "Minimum (cm)",
            borderColor: colors[3],
            backgroundColor: colors[3],
            borderDash: [4, 3],
            data: points.map((point) => ({ ...point, y: point.min })),
          },
          {
            label: "Maksimum (cm)",
            borderColor: colors[2],
            backgroundColor: colors[2],
            borderDash: [4, 3],
            data: points.map((point) => ({ ...point, y: point.max })),
          },
        ].map((dataset) => ({ ...base, ...dataset }))
      : [
          {
            ...base,
            label: "Tinggi air (cm)",
            data: points,
            borderColor: primary,
            backgroundColor: primary,
            pointBackgroundColor: points.map(
              (point) => colors[point.alarm_status ?? 3] ?? colors[3],
            ),
            pointBorderColor: points.map(
              (point) => colors[point.alarm_status ?? 3] ?? colors[3],
            ),
          },
        ];
    if (populated.length) {
      const first = points[0].x;
      const last = points[points.length - 1].x;
      const padding = first === last ? 30000 : 0;
      const options = {
        responsive: true,
        aspectRatio: 1.3,
        animation: false,
        interaction: { mode: "index", intersect: false },
        scales: {
          x: {
            type: "linear",
            min: data.period?.from ?? first - padding,
            max: data.period?.to ?? last + padding,
            title: {
              display: true,
              text: `${labels[unit] ?? "Waktu"} · ${data.timezone}`,
            },
            afterBuildTicks: (scale) => {
              // Label actual calendar buckets rather than arbitrary millisecond intervals.
              const limit = scale.chart.width < 420 ? 4 : 6;
              const step = Math.max(1, Math.ceil(points.length / limit));
              scale.ticks = points
                .filter((_, index) => index % step === 0)
                .map((point) => ({ value: point.x }));
            },
            ticks: {
              autoSkip: true,
              maxRotation: 0,
              callback: (value) => tickFormat.format(new Date(value)),
            },
          },
          y: {
            beginAtZero: true,
            title: { display: true, text: "Tinggi air (cm)" },
          },
        },
        plugins: {
          legend: {
            display: grouped,
            position: "bottom",
            labels: { boxWidth: 16, padding: 10 },
          },
          tooltip: {
            callbacks: {
              title: (items) =>
                grouped && !["minute", "hour"].includes(unit)
                  ? `${labels[unit]} · ${tooltipFormat.format(new Date(items[0].parsed.x))}`
                  : fullDate.format(new Date(items[0].parsed.x)),
              label: (item) =>
                grouped
                  ? `${item.dataset.label}: ${item.parsed.y.toFixed(2)}`
                  : `${item.parsed.y.toFixed(2)} cm · ${item.raw.alarm_status === null ? "Tidak terklasifikasi" : `Siaga ${item.raw.alarm_status + 1}`}`,
              footer: (items) =>
                grouped
                  ? `${items[0].raw.count} pembacaan pada interval ini`
                  : "",
            },
          },
        },
      };
      if (chart) {
        chart.data.datasets = datasets;
        chart.options = options;
        chart.resize();
        chart.update("none");
      } else
        chart = new Chart(canvas, {
          type: "line",
          data: { datasets },
          options,
        });
      const min = Math.min(
        ...populated.map((point) => (grouped ? point.min : point.y)),
      );
      const max = Math.max(
        ...populated.map((point) => (grouped ? point.max : point.y)),
      );
      summary.textContent = `${data.reading_count} pembacaan · ${grouped ? `${populated.length} interval ${labels[unit] ?? unit} berisi data · ` : ""}Minimum ${min.toFixed(2)} cm · Maksimum ${max.toFixed(2)} cm`;
    } else {
      if (chart) {
        chart.data.datasets = [];
        chart.update("none");
      }
      summary.textContent = "Tidak ada riwayat pengukuran pada rentang tanggal ini.";
    }
    note.textContent = grouped
      ? `${data.filters.from} sampai ${data.filters.to} (termasuk kedua tanggal) · Rata-rata ${labels[unit]} beserta minimum dan maksimum. Interval kosong ditampilkan sebagai celah. Waktu menggunakan ${data.timezone}. Diperbarui setiap 5 detik.`
      : `${data.limit} pembacaan terbaru. Waktu menggunakan ${data.timezone}. Diperbarui setiap 5 detik.`;
    if (typeof data.html === "string")
      document.querySelector("#history-device").innerHTML = data.html;
  }

  async function loadHistory(filters) {
    if (busy || stopped) return;
    clearTimeout(timer);
    const requestRevision = ++revision;
    const requestController = new AbortController();
    controller = requestController;
    busy = true;
    controls();
    status.textContent = "Memuat riwayat tinggi air yang dipilih…";
    error.hidden = true;
    const timeout = setTimeout(() => requestController.abort(), 15000);
    let failureMessage = "Riwayat gagal dimuat. Silakan coba lagi.";
    try {
      const response = await fetch(
        `/api/device-history.php?${new URLSearchParams({ device_id: deviceId, ...filters })}`,
        {
          cache: "no-store",
          signal: requestController.signal,
        },
      );
      const data = await response.json();
      if (requestRevision !== revision || stopped) return;
      if (response.status === 401 || response.status === 403) {
        stopped = true;
        error.textContent =
          "Sesi berakhir. Masuk kembali untuk melanjutkan. Filter saat ini tetap disimpan.";
        const link = document.createElement("a");
        link.href = "/login.php";
        link.textContent = " Masuk";
        error.append(link);
        error.hidden = false;
        status.textContent = "Sesi berakhir";
        return;
      }
      if (!response.ok || data.status !== "success") {
        if (response.status === 422 && typeof data.message === "string")
          failureMessage = data.message
            .replace("Provide valid From and To dates in YYYY-MM-DD format.", "Isi tanggal awal dan akhir yang valid dalam format YYYY-MM-DD.")
            .replace("From date must be on or before To date.", "Tanggal awal tidak boleh melewati tanggal akhir.")
            .replace("Choose Auto, Minute, Hour, Day, Month, or Year grouping.", "Pilih pengelompokan otomatis, menit, jam, hari, bulan, atau tahun.")
            .replace("Minute grouping requires a single date. Set From date and To date to the same date.", "Pengelompokan per menit memerlukan satu tanggal. Samakan tanggal awal dan tanggal akhir.")
            .replace("This grouping would create more than ", "Pengelompokan ini menghasilkan lebih dari ")
            .replace(" points. Shorten the date range or choose a larger grouping.", " titik. Perpendek rentang tanggal atau pilih kelompok yang lebih besar.");
        throw new Error("History unavailable");
      }
      render(data);
      appliedFilters = data.filters;
      const url = new URL(window.location.href);
      for (const [name, value] of Object.entries(appliedFilters))
        url.searchParams.set(name, value);
      window.history.replaceState(null, "", url);
      status.textContent = "Diperbarui " + new Date().toLocaleTimeString("id-ID");
    } catch (_) {
      if (!stopped && requestRevision === revision) {
        error.textContent = `${failureMessage} Grafik terakhir tetap ditampilkan.`;
        error.hidden = false;
        status.textContent = "Pembaruan riwayat gagal";
      }
    } finally {
      clearTimeout(timeout);
      if (requestRevision === revision) {
        busy = false;
        controls();
        schedule();
      }
    }
  }

  function editFilters() {
    // Editing cancels the old request so its response cannot overwrite the draft.
    revision++;
    controller?.abort();
    busy = false;
    form.elements.to.setCustomValidity("");
    error.hidden = true;
    status.textContent = dirty()
      ? "Menampilkan rentang tanggal terakhir yang diterapkan"
      : "Diperbarui setiap 5 detik";
    controls();
    schedule();
  }

  refresh.addEventListener("click", () => {
    if (typeof Chart === "undefined") window.location.reload();
    else if (!dirty()) loadHistory(appliedFilters);
  });
  if (typeof Chart === "undefined") {
    error.textContent =
      "Grafik gagal dimuat. Muat ulang halaman untuk mencoba lagi.";
    error.hidden = false;
    status.textContent = "Grafik tidak tersedia";
    refresh.textContent = "Muat ulang halaman";
    apply.disabled = reset.disabled = true;
    content.setAttribute("aria-busy", "false");
    return;
  }
  form.addEventListener("input", editFilters);
  form.addEventListener("change", editFilters);
  form.addEventListener("submit", (event) => {
    event.preventDefault();
    if (busy) return;
    const filters = readFilters();
    form.elements.to.setCustomValidity(
      filters.from > filters.to ? "Tanggal akhir tidak boleh sebelum tanggal awal." : "",
    );
    if (!form.reportValidity()) return;
    if (filters.group_by === "minute" && filters.from !== filters.to) {
      error.textContent =
        "Pengelompokan per menit memerlukan satu tanggal. Samakan tanggal awal dan tanggal akhir.";
      error.hidden = false;
      status.textContent = "Filter tanggal tidak valid";
      error.focus();
      return;
    }
    loadHistory(filters);
  });
  reset.addEventListener("click", () => {
    if (
      busy ||
      (dirty() && !window.confirm("Buang filter yang belum diterapkan dan tampilkan hari ini?"))
    )
      return;
    form.elements.from.value = form.elements.to.value = form.dataset.today;
    form.elements.group_by.value = "auto";
    editFilters();
    loadHistory(readFilters());
  });
  window.addEventListener("beforeunload", (event) => {
    if (dirty()) {
      event.preventDefault();
      event.returnValue = "";
    }
  });
  // Canvas styling shares the same tokens as the HTML components.
  Chart.defaults.color = getComputedStyle(document.documentElement)
    .getPropertyValue("--body-mid")
    .trim();
  Chart.defaults.borderColor = getComputedStyle(document.documentElement)
    .getPropertyValue("--grid-color")
    .trim();
  Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
  Chart.defaults.font.size = parseFloat(
    getComputedStyle(document.documentElement).getPropertyValue("--font-xs"),
  );
  Chart.defaults.plugins.tooltip.backgroundColor = getComputedStyle(
    document.documentElement,
  )
    .getPropertyValue("--primary")
    .trim();
  Chart.defaults.plugins.tooltip.titleFont.weight = getComputedStyle(
    document.documentElement,
  )
    .getPropertyValue("--weight-semibold")
    .trim();
  Chart.defaults.plugins.tooltip.footerFont.weight = getComputedStyle(
    document.documentElement,
  )
    .getPropertyValue("--weight-medium")
    .trim();
  render(initial);
  controls();
  status.textContent = "Diperbarui setiap 5 detik";
  schedule();
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
