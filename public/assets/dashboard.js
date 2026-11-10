let dashboardAccessExpired = false;
const siagaAlert = document.querySelector("#siaga-alert");
const siagaDismissed = new Set();
let siagaActive = new Set();
let siagaVisible = new Set();
function syncSiagaAlert() {
  if (!siagaAlert) return;
  const alertCards = [...document.querySelectorAll('#water-level-panels [data-alert-status="0"], #water-level-panels [data-alert-status="1"], #water-level-panels [data-alert-status="2"]')];
  const key = (card) => `${card.dataset.alertDevice}:${card.dataset.alertStatus}`;
  siagaActive = new Set(alertCards.map(key));
  const level = alertCards.some((card) => card.dataset.alertStatus === "2") ? "2" : (alertCards.some((card) => card.dataset.alertStatus === "1") ? "1" : "0");
  const cards = alertCards.filter((card) => card.dataset.alertStatus === level);
  siagaVisible = new Set(cards.map(key));
  siagaAlert.dataset.severity = ["info", "warning", "danger"][Number(level)];
  document.querySelector("#siaga-alert-title").textContent = ["INFO", "PERINGATAN", "BAHAYA"][Number(level)];
  document.querySelector("#siaga-alert-description").textContent = `SIAGA ${Number(level) + 1}`;
  siagaAlert.querySelector(".flow-alert-symbol").className = `fa-solid ${level === "0" ? "fa-circle-info" : "fa-triangle-exclamation"} flow-alert-symbol`;
  for (const id of siagaDismissed) {
    if (!siagaActive.has(id)) siagaDismissed.delete(id);
  }
  const list = document.querySelector("#siaga-alert-devices");
  list.replaceChildren();
  for (const card of cards) {
    const item = document.createElement("li");
    const name = card.querySelector("h2").textContent.trim();
    item.textContent = /^pos pantau\b/i.test(name) ? name : `Pos Pantau ${name}`;
    list.append(item);
  }
  if (!cards.length || dashboardAccessExpired || ![...siagaVisible].some((id) => !siagaDismissed.has(id))) {
    if (siagaAlert.open) siagaAlert.close();
  } else if (!siagaAlert.open) {
    siagaAlert.showModal();
  }
}
function dismissSiagaAlert() {
  for (const id of siagaVisible) siagaDismissed.add(id);
  siagaAlert.close();
}
if (siagaAlert) {
  document.querySelector("#siaga-alert-close").addEventListener("click", dismissSiagaAlert);
  siagaAlert.addEventListener("cancel", (event) => {
    event.preventDefault();
    dismissSiagaAlert();
  });
  syncSiagaAlert();
  window.addEventListener("pageshow", (event) => {
    if (event.persisted) {
      siagaDismissed.clear();
      syncSiagaAlert();
    }
  });
}
async function refreshDashboard() {
  const status = document.querySelector("#refresh-status");
  const panels = document.querySelector("#water-level-panels");
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 15000);
  panels.setAttribute("aria-busy", "true");
  status.textContent = "Memperbarui pembacaan perangkat…";
  try {
    const response = await fetch(
      panels.dataset.endpoint || "/api/dashboard.php",
      {
        cache: "no-store",
        signal: controller.signal,
      },
    );
    if (response.status === 401 || response.status === 403) {
      dashboardAccessExpired = true;
      if (siagaAlert?.open) siagaAlert.close();
      status.textContent = "Sesi berakhir. Masuk kembali untuk melanjutkan.";
      const link = document.createElement("a");
      link.href = "/login.php";
      link.textContent = " Masuk";
      status.append(link);
      return;
    }
    if (!response.ok) throw new Error("HTTP " + response.status);
    const data = await response.json();
    if (data.status !== "success") throw new Error("Invalid response");
    panels.innerHTML = data.html;
    syncSiagaAlert();
    document.querySelector("#stat-total").textContent = data.total;
    document.querySelector("#stat-online").textContent = data.online;
    document.querySelector("#stat-offline").textContent =
      data.total - data.online;
    status.textContent = "Diperbarui " + new Date().toLocaleTimeString("id-ID");
  } catch (_) {
    status.textContent = "Koneksi terputus · mencoba lagi";
  } finally {
    clearTimeout(timeout);
    panels.setAttribute("aria-busy", "false");
    if (!dashboardAccessExpired) setTimeout(refreshDashboard, 5000);
  }
}
setTimeout(refreshDashboard, 5000);
