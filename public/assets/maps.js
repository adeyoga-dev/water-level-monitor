(() => {
  const container = document.querySelector("#devices-map");
  const status = document.querySelector("#map-status");
  const error = document.querySelector("#map-error");
  const reset = document.querySelector("#map-indonesia");
  const focusButtons = [...document.querySelectorAll("[data-map-device]")];
  if (typeof L === "undefined") {
    container.setAttribute("aria-busy", "false");
    status.textContent = "Map unavailable";
    error.textContent =
      "The map library could not be loaded. Reload this page to try again. Device locations are still listed below.";
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
    scrollWheelZoom: true,
    zoomSnap: 0.25,
    minZoom: 2,
    maxZoom: 19,
  });
  const markers = new Map();
  let overview = true;
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
      '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
  });
  tiles.on("loading", () => {
    clearTimeout(tileTimer);
    tileErrors = 0;
    container.setAttribute("aria-busy", "true");
    status.textContent = "Loading map tiles…";
    tileTimer = setTimeout(() => {
      container.setAttribute("aria-busy", "false");
      status.textContent = "Map tiles are taking longer than expected";
      error.textContent =
        "Check your internet connection or reload this page. Device pins and the list remain available.";
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
      ? "Some map tiles could not be loaded"
      : `${devices.length} device locations · Select a pin for details`;
    error.hidden = tileErrors === 0;
    if (tileErrors)
      error.textContent =
        "Some background tiles are unavailable. Check your connection or reload this page. Device pins and the list remain available.";
  });
  tiles.addTo(map);
  for (const device of devices) {
    const popup = document.createElement("div");
    popup.className = "flow-map-popup";
    const title = document.createElement("h3");
    title.className = "h6";
    title.textContent = device.device_id;
    const badge = document.createElement("span");
    badge.className = `badge badge-${device.online ? "success" : "secondary"}`;
    badge.textContent = device.online ? "Online" : "Offline";
    const reading = document.createElement("p");
    reading.className = "mb-2 mt-2";
    reading.textContent =
      device.level === null
        ? "No readings yet"
        : `${device.level.toFixed(1)} cm · ${device.alert}`;
    const location = document.createElement("p");
    location.className = "small text-muted mb-2";
    location.textContent = `${device.latitude}, ${device.longitude}`;
    const seen = document.createElement("p");
    seen.className = "small text-muted mb-2";
    seen.textContent = `Last measurement: ${device.last_seen}`;
    const edit = document.createElement("a");
    edit.className = "btn btn-outline-secondary";
    edit.href = `/device-edit.php?id=${encodeURIComponent(device.id)}`;
    edit.textContent = "Edit location";
    const view = document.createElement("a");
    view.className = "btn btn-primary";
    view.href = container.dataset.viewer === "true" ? "/viewer.php" : "/";
    view.textContent = "View";
    const zoomOut = document.createElement("button");
    zoomOut.type = "button";
    zoomOut.className = "btn btn-primary";
    zoomOut.textContent = "Zoom in";
    zoomOut.addEventListener("click", () => {
      map.closePopup();
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
      title: `${device.device_id} · ${device.online ? "Online" : "Offline"}`,
      alt: device.device_id,
      icon: L.divIcon({
        className: `flow-map-marker ${device.online ? "is-online" : "is-offline"}`,
        html: '<span class="flow-map-pin" aria-hidden="true"></span>',
        iconSize: [markerSize, markerSize],
        iconAnchor: [markerSize / 2, markerSize],
        popupAnchor: [0, -markerSize],
      }),
    })
      .addTo(map)
      .bindPopup(popup, { maxWidth: 260 });
    markers.set(String(device.id), marker);
  }
  reset.disabled = false;
  reset.addEventListener("click", () => {
    map.closePopup();
    showIndonesia();
  });
  for (const button of focusButtons) {
    const marker = markers.get(button.dataset.mapDevice);
    if (!marker) continue;
    button.disabled = false;
    button.addEventListener("click", () => {
      overview = false;
      container.scrollIntoView({
        behavior: reducedMotion.matches ? "auto" : "smooth",
        block: "center",
      });
      map.setView(marker.getLatLng(), 14, { animate: false });
      marker.openPopup();
      marker.getElement()?.focus({ preventScroll: true });
    });
  }
  window.addEventListener("pagehide", () => {
    clearTimeout(tileTimer);
    map.remove();
  });
  window.addEventListener("pageshow", (event) => {
    if (event.persisted) window.location.reload();
  });
})();
