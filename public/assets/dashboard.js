let dashboardAccessExpired = false;
async function refreshDashboard() {
  const status = document.querySelector("#refresh-status");
  const panels = document.querySelector("#water-level-panels");
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 15000);
  panels.setAttribute("aria-busy", "true");
  status.textContent = "Updating device readings…";
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
      status.textContent = "Access ended. Sign in again to continue.";
      const link = document.createElement("a");
      link.href = "/login.php";
      link.textContent = " Sign in";
      status.append(link);
      return;
    }
    if (!response.ok) throw new Error("HTTP " + response.status);
    const data = await response.json();
    if (data.status !== "success") throw new Error("Invalid response");
    panels.innerHTML = data.html;
    document.querySelector("#stat-total").textContent = data.total;
    document.querySelector("#stat-online").textContent = data.online;
    document.querySelector("#stat-offline").textContent =
      data.total - data.online;
    status.textContent = "Updated " + new Date().toLocaleTimeString("en-GB");
  } catch (_) {
    status.textContent = "Connection lost · retrying";
  } finally {
    clearTimeout(timeout);
    panels.setAttribute("aria-busy", "false");
    if (!dashboardAccessExpired) setTimeout(refreshDashboard, 5000);
  }
}
setTimeout(refreshDashboard, 5000);
