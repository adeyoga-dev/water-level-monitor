async function refreshDashboard() {
  const status = document.querySelector("#refresh-status");
  const table = document.querySelector("#device-table");
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 15000);
  table.setAttribute("aria-busy", "true");
  status.textContent = "Updating device readings…";
  try {
    const response = await fetch("/api/dashboard.php", {
      cache: "no-store",
      signal: controller.signal,
    });
    if (!response.ok) throw new Error("HTTP " + response.status);
    const data = await response.json();
    if (data.status !== "success") throw new Error("Invalid response");
    table.innerHTML = data.html;
    document.querySelector("#stat-total").textContent = data.total;
    document.querySelector("#stat-online").textContent = data.online;
    document.querySelector("#stat-offline").textContent =
      data.total - data.online;
    document.querySelector("#stat-logs").textContent = data.logs;
    status.textContent = "Updated " + new Date().toLocaleTimeString("en-GB");
  } catch (_) {
    status.textContent = "Connection lost · retrying";
  } finally {
    clearTimeout(timeout);
    table.setAttribute("aria-busy", "false");
    setTimeout(refreshDashboard, 5000);
  }
}
setTimeout(refreshDashboard, 5000);
