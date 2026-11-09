(() => {
  const container = document.querySelector("#devices-map");
  const status = document.querySelector("#map-status");
  const error = document.querySelector("#map-error");
  const reset = document.querySelector("#map-indonesia");
  if (typeof L === "undefined") {
    container.setAttribute("aria-busy", "false");
    status.textContent = "Peta tidak tersedia";
    error.textContent =
      "Pustaka peta gagal dimuat. Muat ulang halaman untuk mencoba lagi.";
    error.hidden = false;
    return;
  }
  const devices = JSON.parse(document.querySelector("#map-data").textContent);
  const tokens = getComputedStyle(document.documentElement);
  const padding = parseFloat(tokens.getPropertyValue("--space-6"));
  const markerSize = parseFloat(tokens.getPropertyValue("--map-marker-size"));
  const indonesia = L.latLngBounds([
    [-11.5, 94.5],
    [6.5, 141.5],
  ]);
  const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");
  const map = L.map(container, {
    zoomControl: false,
    scrollWheelZoom: true,
    zoomSnap: 0.25,
    minZoom: 2,
    maxZoom: 19,
  });
  L.control.zoom({ zoomInTitle: "Perbesar peta", zoomOutTitle: "Perkecil peta" }).addTo(map);
  const markers = new Map();
  let allPopupsOpen = false;
  let hoverTimer;
  function closePopups() {
    clearTimeout(hoverTimer);
    allPopupsOpen = false;
    for (const marker of markers.values()) marker.closePopup();
  }
  function previewMarker(marker) {
    clearTimeout(hoverTimer);
    if (allPopupsOpen) return;
    closePopups();
    marker.openPopup();
  }
  function endPreview(marker) {
    clearTimeout(hoverTimer);
    hoverTimer = setTimeout(() => {
      if (!allPopupsOpen) marker.closePopup();
    }, parseFloat(tokens.getPropertyValue("--map-hover-delay")));
  }
  let overview = true;
  function focusMarker(marker) {
    overview = false;
    container.scrollIntoView({
      behavior: reducedMotion.matches ? "auto" : "smooth",
      block: "center",
    });
    map.setView(marker.getLatLng(), 14, { animate: false });
    closePopups();
    marker.openPopup();
    marker.getElement()?.focus({ preventScroll: true });
  }
  let tileErrors = 0;
  let tileTimer;
  function showIndonesia() {
    map.fitBounds(indonesia, { padding: [padding, padding], animate: false });
    overview = true;
  }
  showIndonesia();
  map.on("movestart", () => {
    overview = false;
  });
  map.on("resize", () => {
    if (overview) showIndonesia();
  });
  const tiles = L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
    maxZoom: 19,
    attribution:
      '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> kontributor',
  });
  tiles.on("loading", () => {
    clearTimeout(tileTimer);
    tileErrors = 0;
    container.setAttribute("aria-busy", "true");
    status.textContent = "Memuat gambar peta…";
    tileTimer = setTimeout(() => {
      container.setAttribute("aria-busy", "false");
      status.textContent = "Gambar peta membutuhkan waktu lebih lama dari biasanya";
      error.textContent =
        "Periksa koneksi internet atau muat ulang halaman. Penanda tetap tersedia.";
      error.hidden = false;
    }, 15000);
  });
  tiles.on("tileerror", () => {
    tileErrors++;
  });
  tiles.on("load", () => {
    clearTimeout(tileTimer);
    container.setAttribute("aria-busy", "false");
    status.textContent = tileErrors
      ? "Sebagian gambar peta gagal dimuat"
      : `${devices.length} lokasi perangkat · Arahkan penunjuk untuk melihat informasi; klik pin untuk menampilkan atau menutup semua informasi`;
    error.hidden = tileErrors === 0;
    if (tileErrors)
      error.textContent =
        "Sebagian gambar peta tidak tersedia. Periksa koneksi atau muat ulang halaman. Penanda tetap tersedia.";
  });
  tiles.addTo(map);
  for (const device of devices) {
    const popup = document.createElement("div");
    popup.className = "flow-map-popup";
    const title = document.createElement("h3");
    title.className = "h6";
    title.textContent = device.station_name;
    const badge = document.createElement("span");
    badge.className = `badge badge-${device.online ? "success" : "danger"}`;
    badge.textContent = device.online ? "Terhubung" : "Terputus";
    const reading = document.createElement("p");
    reading.className = "mb-2 mt-2";
    reading.textContent =
      device.level === null
        ? "Belum ada pembacaan"
        : `Tinggi air: ${device.level.toFixed(1)} cm `;
    if (device.level !== null) {
      const alertBadge = document.createElement("span");
      const alertColor = {
        "Siaga 1": "success",
        "Siaga 2": "warning",
        "Siaga 3": "danger",
      }[device.alert] || "secondary";
      alertBadge.className = `badge badge-${alertColor}`;
      alertBadge.textContent = device.alert;
      reading.append(alertBadge);
    }
    const location = document.createElement("p");
    location.className = "small text-muted mb-2";
    location.textContent = `${device.latitude}, ${device.longitude}`;
    const seen = document.createElement("p");
    seen.className = "small text-muted mb-2";
    seen.textContent = `Pengukuran terakhir: ${device.last_seen}`;
    const edit = document.createElement("a");
    edit.className = "btn btn-outline-secondary";
    edit.href = `/device-edit.php?id=${encodeURIComponent(device.id)}`;
    edit.textContent = "Ubah lokasi";
    const view = document.createElement("button");
    view.type = "button";
    view.className = "btn btn-primary";
    view.textContent = "Lihat";
    view.addEventListener("click", () => focusMarker(marker));
    const zoomOut = document.createElement("button");
    zoomOut.type = "button";
    zoomOut.className = "btn btn-primary";
    zoomOut.textContent = "Perbesar";
    zoomOut.addEventListener("click", () => {
      closePopups();
      map.setView([device.latitude, device.longitude], map.getMaxZoom(), {
        animate: false,
      });
      container.focus({ preventScroll: true });
    });
    const primaryActions = document.createElement("div");
    primaryActions.className = "d-flex flex-nowrap flow-map-actions";
    primaryActions.append(view, zoomOut);
    const actions = document.createElement("div");
    actions.className = "d-flex flex-wrap flow-map-actions";
    actions.append(primaryActions);
    if (container.dataset.viewer !== "true") actions.append(edit);
    popup.append(title, badge, reading, location);
    if (container.dataset.viewer !== "true") popup.append(seen);
    popup.append(actions);
    const marker = L.marker([device.latitude, device.longitude], {
      title: `${device.station_name} · ${device.online ? "Terhubung" : "Terputus"}`,
      alt: device.station_name,
      icon: L.divIcon({
        className: `flow-map-marker ${device.online ? "is-online" : "is-offline"}`,
        html: '<span class="flow-map-pin" aria-hidden="true"></span>',
        iconSize: [markerSize, markerSize],
        iconAnchor: [markerSize / 2, markerSize],
        popupAnchor: [0, -markerSize],
      }),
    })
      .addTo(map)
      .bindPopup(popup, {
        maxWidth: 260,
        autoClose: false,
        closeOnClick: false,
        autoPan: false,
      });
    // Replace Leaflet's single-popup click toggle with the shared toggle.
    marker.off("click");
    marker.on("click", () => {
      if (allPopupsOpen) {
        closePopups();
      } else {
        clearTimeout(hoverTimer);
        allPopupsOpen = true;
        for (const item of markers.values()) item.openPopup();
      }
    });
    marker.on("mouseover", () => previewMarker(marker));
    marker.on("mouseout", () => endPreview(marker));
    marker.getElement().addEventListener("focus", () => previewMarker(marker));
    marker.getElement().addEventListener("blur", () => endPreview(marker));
    popup.addEventListener("mouseenter", () => clearTimeout(hoverTimer));
    popup.addEventListener("mouseleave", () => endPreview(marker));
    popup.addEventListener("focusin", () => clearTimeout(hoverTimer));
    popup.addEventListener("focusout", (event) => {
      if (!popup.contains(event.relatedTarget)) endPreview(marker);
    });
    marker.on("popupclose", () => {
      if (allPopupsOpen) closePopups();
    });
    markers.set(String(device.id), marker);
  }
  reset.disabled = false;
  map.on("click", closePopups);
  container.addEventListener("keydown", (event) => {
    if (event.key === "Escape") closePopups();
  });
  reset.addEventListener("click", () => {
    closePopups();
    showIndonesia();
  });
  window.addEventListener("pagehide", () => {
    clearTimeout(hoverTimer);
    clearTimeout(tileTimer);
    map.remove();
  });
  window.addEventListener("pageshow", (event) => {
    if (event.persisted) window.location.reload();
  });
})();
