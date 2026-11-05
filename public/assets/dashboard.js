async function refreshDashboard() {
  const status = document.querySelector("#refresh-status");
  try {
    const response = await fetch("/api/dashboard.php", { cache: "no-store" });
    if (!response.ok) throw new Error("HTTP " + response.status);
    const data = await response.json();
    if (data.status !== "success") throw new Error("Invalid response");
    document.querySelector("#device-rows").closest(".table-scroll").outerHTML =
      data.html;
    document.querySelector("#stat-total").textContent = data.total;
    document.querySelector("#stat-online").textContent = data.online;
    document.querySelector("#stat-offline").textContent =
      data.total - data.online;
    document.querySelector("#stat-logs").textContent = data.logs;
    status.textContent = "Updated " + new Date().toLocaleTimeString("en-GB");
  } catch (_) {
    status.textContent = "Connection lost · retrying";
  } finally {
    setTimeout(refreshDashboard, 5000);
  }
}
setTimeout(refreshDashboard, 5000);
