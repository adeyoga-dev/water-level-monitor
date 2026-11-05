import assert from "node:assert/strict";
import { spawn } from "node:child_process";
import { mkdir, writeFile } from "node:fs/promises";
import net from "node:net";
import path from "node:path";
import { fileURLToPath } from "node:url";

const taskDir = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(taskDir, "../..");
await mkdir(path.join(taskDir, "screenshots"), { recursive: true });
const checks = [];
let php;
let chrome;
let socket;
let browserSession;
const pending = new Map();
const listeners = [];
let sequence = 0;

function send(method, params = {}, sessionId = browserSession) {
  const id = ++sequence;
  return new Promise((resolve, reject) => {
    const timeout = setTimeout(() => { pending.delete(id); reject(new Error(`CDP timeout: ${method}`)); }, 25000);
    pending.set(id, { resolve, reject, timeout });
    socket.send(JSON.stringify({ id, method, params, ...(sessionId ? { sessionId } : {}) }));
  });
}

async function evaluate(expression) {
  const result = await send("Runtime.evaluate", { expression, returnByValue: true, awaitPromise: true });
  if (result.exceptionDetails) throw new Error(JSON.stringify(result.exceptionDetails));
  return result.result.value;
}

async function waitFor(expression) {
  assert.equal(await evaluate(`new Promise(resolve => {
    const started = Date.now();
    const timer = setInterval(() => {
      if (${expression}) { clearInterval(timer); resolve(true); }
      else if (Date.now() - started > 21000) { clearInterval(timer); resolve(false); }
    }, 50);
  })`), true, expression);
}

function loaded() {
  return new Promise((resolve, reject) => {
    const timeout = setTimeout(() => reject(new Error("Page load timed out")), 15000);
    listeners.push({ method: "Page.loadEventFired", callback: () => { clearTimeout(timeout); resolve(); } });
  });
}

try {
  const port = await new Promise((resolve, reject) => {
    const server = net.createServer();
    server.once("error", reject);
    server.listen(0, "127.0.0.1", () => { const port = server.address().port; server.close(() => resolve(port)); });
  });
  const base = `http://127.0.0.1:${port}`;
  php = spawn("php", ["-S", `127.0.0.1:${port}`, "-t", path.join(root, "public"), path.join(taskDir, "router.php")], { cwd: root, windowsHide: true, stdio: ["ignore", "ignore", "pipe"] });
  await new Promise((resolve, reject) => {
    php.once("error", reject);
    php.stderr.on("data", chunk => { if (chunk.toString().includes("started")) resolve(); });
    php.once("exit", code => reject(new Error(`PHP exited: ${code}`)));
  });
  chrome = spawn("C:/Program Files/Google/Chrome/Application/chrome.exe", ["--headless=new", "--disable-gpu", "--no-first-run", "--no-default-browser-check", "--remote-debugging-port=0", `--user-data-dir=${path.join(taskDir, "chrome-profile")}`, "about:blank"], { windowsHide: true, stdio: ["ignore", "ignore", "pipe"] });
  const debuggerUrl = await new Promise((resolve, reject) => {
    let stderr = "";
    const timeout = setTimeout(() => reject(new Error("Chrome debugger not available")), 20000);
    chrome.once("error", reject);
    chrome.stderr.on("data", chunk => {
      stderr += chunk.toString();
      const match = stderr.match(/DevTools listening on (ws:\/\/[^\s]+)/);
      if (match) { clearTimeout(timeout); resolve(match[1]); }
    });
    chrome.once("exit", code => reject(new Error(`Chrome exited: ${code}`)));
  });
  socket = new WebSocket(debuggerUrl);
  await new Promise((resolve, reject) => { socket.addEventListener("open", resolve, { once: true }); socket.addEventListener("error", reject, { once: true }); });
  socket.addEventListener("message", event => {
    const message = JSON.parse(event.data);
    if (message.id && pending.has(message.id)) {
      const entry = pending.get(message.id);
      pending.delete(message.id);
      clearTimeout(entry.timeout);
      if (message.error) entry.reject(new Error(JSON.stringify(message.error)));
      else entry.resolve(message.result);
    }
    for (let i = listeners.length - 1; i >= 0; i--) {
      if (listeners[i].method === message.method) {
        const entry = listeners.splice(i, 1)[0];
        entry.callback(message);
      }
    }
  });
  const { targetId } = await send("Target.createTarget", { url: "about:blank" }, null);
  browserSession = (await send("Target.attachToTarget", { targetId, flatten: true }, null)).sessionId;
  await send("Page.enable");
  await send("Runtime.enable");
  await send("Network.enable");
  async function navigate(route) {
    const load = loaded();
    await send("Page.navigate", { url: base + route });
    await load;
  }
  const routes = ["/", "/devices.php", "/device-edit.php", "/device-edit.php?id=1", "/logs.php", "/firmwares.php"];
  for (const [size, width, height] of [["desktop", 1440, 900], ["mobile", 390, 844]]) {
    await send("Emulation.setDeviceMetricsOverride", { width, height, deviceScaleFactor: 1, mobile: size === "mobile" });
    for (const route of routes) {
      await navigate(route);
      const state = await evaluate(`({
        title: document.title,
        stylesheet: [...document.styleSheets].map(sheet => sheet.href),
        overflow: document.documentElement.scrollWidth > window.innerWidth + 1,
        inline: document.querySelectorAll('[style], style').length,
        active: document.querySelectorAll('nav[aria-label="Main navigation"] [aria-current="page"]').length,
        buttonColor: document.querySelector('.btn-primary') ? getComputedStyle(document.querySelector('.btn-primary')).backgroundColor : null,
        sidebar: document.querySelector('aside').getBoundingClientRect().toJSON(),
        main: document.querySelector('main').getBoundingClientRect().toJSON(),
        unlabeled: [...document.querySelectorAll('input:not([type="hidden"]), select, textarea')].filter(input => !input.labels?.length).length,
        scripts: document.querySelectorAll('script:not([src])').length,
      })`);
      assert.deepEqual(state.stylesheet, [base + "/assets/vendor/bootstrap-4.6.2-dist/css/bootstrap.min.css"]);
      assert.equal(state.inline, 0);
      assert.equal(state.active, 1);
      assert.equal(state.unlabeled, 0);
      assert.equal(state.scripts, 0, "changelog must remain escaped");
      assert.equal(state.overflow, false, `${size} document overflow: ${route}`);
      if (state.buttonColor) assert.equal(state.buttonColor, "rgb(0, 123, 255)");
      if (size === "mobile") assert.ok(state.main.y >= state.sidebar.bottom);
      else assert.ok(state.main.x >= state.sidebar.right);
      const name = route === "/" ? "dashboard" : route.replaceAll(/[/?=]/g, "-").replace(/^-/, "");
      const shot = await send("Page.captureScreenshot", { format: "png", captureBeyondViewport: true });
      await writeFile(path.join(taskDir, "screenshots", `${size}-${name}.png`), Buffer.from(shot.data, "base64"));
      checks.push(`${size}: ${route} layout, local CSS, labels, active menu, no page overflow`);
    }
  }
  await navigate("/logs.php?device_id=ESP32-001&alarm_status=1&from=2026-11-04&to=2026-11-05");
  assert.equal(await evaluate(`new URL(document.querySelector('a.page-link').href).searchParams.get('device_id')`), "ESP32-001");
  assert.equal(await evaluate(`document.querySelector('#filter-alert').value`), "1");
  checks.push("log filters retained in controls and pagination links");
  for (const [route, message] of [["/?empty", "No devices yet"], ["/logs.php?empty", "No logs match"], ["/firmwares.php?empty", "No firmware has"]]) {
    await navigate(route);
    assert.ok(await evaluate(`document.querySelector('main').textContent.includes(${JSON.stringify(message)})`));
    checks.push(`empty state: ${route}`);
  }
  await navigate("/device-edit.php?error");
  assert.equal(await evaluate(`document.querySelector('[role="alert"]').classList.contains('alert-danger')`), true);
  assert.equal(await evaluate(`document.querySelector('#device-id').value`), "ESP32-001");
  checks.push("validation alert and preserved device input render correctly");
  await navigate("/devices.php?success");
  assert.equal(await evaluate(`document.querySelector('[role="status"]').classList.contains('alert-success')`), true);
  checks.push("success flash uses Bootstrap alert");
  await navigate("/");
  await waitFor(`document.querySelector('#refresh-status').textContent.startsWith('Updated ')`);
  assert.equal(await evaluate(`document.querySelector('#device-table').getAttribute('aria-busy')`), "false");
  assert.equal(await evaluate(`document.querySelectorAll('#device-rows tr').length`), 2);
  checks.push("dashboard polling replaces Bootstrap table and clears loading");
  await send("Network.setBlockedURLs", { urls: ["*api/dashboard.php*"] });
  await waitFor(`document.querySelector('#refresh-status').textContent.includes('Connection lost')`);
  assert.equal(await evaluate(`document.querySelectorAll('#device-rows tr').length`), 2);
  assert.equal(await evaluate(`document.querySelector('#device-table').getAttribute('aria-busy')`), "false");
  checks.push("failed refresh preserves readings and clears loading");
  await send("Network.setBlockedURLs", { urls: [] });
  await waitFor(`document.querySelector('#refresh-status').textContent.startsWith('Updated ')`);
  checks.push("polling recovers automatically after network failure");
  await send("Fetch.enable", { patterns: [{ urlPattern: "*api/dashboard.php*", requestStage: "Request" }] });
  await waitFor(`document.querySelector('#device-table').getAttribute('aria-busy') === 'true'`);
  await waitFor(`document.querySelector('#refresh-status').textContent.includes('Connection lost')`);
  assert.equal(await evaluate(`document.querySelector('#device-table').getAttribute('aria-busy')`), "false");
  checks.push("15-second request timeout clears loading and preserves readings");
  await send("Fetch.disable");
  await waitFor(`document.querySelector('#refresh-status').textContent.startsWith('Updated ')`);
  checks.push("polling recovers after timeout cancellation");
  await writeFile(path.join(taskDir, "verification.json"), JSON.stringify({ fixtureOnly: true, checks }, null, 2) + "\n");
  console.log(`${checks.length} browser fixture checks passed. Screenshots: temp/bootstrap-ui/screenshots/`);
} finally {
  if (socket?.readyState === WebSocket.OPEN) {
    try { await send("Browser.close", {}, null); } catch {}
    socket.close();
  }
  chrome?.kill();
  php?.kill();
}
