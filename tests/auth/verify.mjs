import assert from "node:assert/strict";
import { spawn } from "node:child_process";
import { mkdir, writeFile, readFile, rm } from "node:fs/promises";
import { randomBytes } from "node:crypto";
import net from "node:net";
import path from "node:path";
import { fileURLToPath } from "node:url";

const sourceDir = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(sourceDir, "../..");
const taskDir = path.join(root, "temp/auth-tests");
await mkdir(path.join(taskDir, "screenshots"), { recursive: true });
const checks = [];
let php;
let mysql;
let heldRequests = [];
let holdHistory = false;
let failLibrary = false;
let allowDiscard = false;
let failTiles = false;
let failMapLibrary = false;
const runId = randomBytes(6).toString('hex');
const dataDir = path.join(taskDir, 'mysql-data-' + runId);
const profileDir = path.join(taskDir, 'chrome-profile-' + runId);
let fixtureEnv;
const chartUrl = '/assets/vendor/chartjs-4.6.1/chart.umd.min.js';
const externalBlocks = [];
async function run(command, args, env = process.env) {
  const child = spawn(command, args, { cwd: root, windowsHide: true, env, stdio: ['ignore', 'pipe', 'pipe'] });
  let output = '';
  child.stdout.on('data', chunk => { output += chunk.toString(); });
  child.stderr.on('data', chunk => { output += chunk.toString(); });
  await new Promise((resolve, reject) => { child.once('error', reject); child.once('exit', code => code === 0 ? resolve() : reject(new Error(command + ': ' + output))); });
  return output;
}
async function freePort() {
  return new Promise((resolve, reject) => {
    const server = net.createServer(); server.once('error', reject);
    server.listen(0, '127.0.0.1', () => { const port = server.address().port; server.close(() => resolve(port)); });
  });
}
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
  assert.ok((await readFile(path.join(root, 'public', chartUrl))).toString().includes('Chart.js v4.6.1'));
  const dbPort = await freePort();
  fixtureEnv = { ...process.env, ADMIN_EMAIL: 'admin@example.test', ADMIN_PASSWORD: 'StrongPassword123!', DB_HOST: '127.0.0.1', DB_PORT: String(dbPort), DB_DATABASE: 'water_level_test_history_' + runId, DB_USERNAME: 'root', DB_PASSWORD: '', DB_CHARSET: 'utf8mb4', APP_TIMEZONE: 'Asia/Jakarta', HISTORY_TEST_DATADIR: dataDir };
  assert.notEqual(dbPort, 3306);
  await run('C:/develop/web/mysql/bin/mysql_install_db.exe', ['--datadir=' + dataDir, '--port=' + dbPort]);
  mysql = spawn('C:/develop/web/mysql/bin/mysqld.exe', ['--defaults-file=' + path.join(dataDir, 'my.ini'), '--datadir=' + dataDir, '--bind-address=127.0.0.1', '--port=' + dbPort, '--console'], { cwd: root, windowsHide: true, stdio: ['ignore', 'ignore', 'pipe'] });
  let dbLog = '';
  mysql.stderr.on('data', chunk => { dbLog += chunk.toString(); });
  await new Promise((resolve, reject) => {
    const started = Date.now();
    const probe = () => {
      if (mysql.exitCode !== null) { reject(new Error('Temporary MySQL exited: ' + dbLog)); return; }
      const connection = net.connect({ host: '127.0.0.1', port: dbPort });
      connection.once('connect', () => { connection.destroy(); resolve(); });
      connection.once('error', () => {
        if (Date.now() - started > 20000) reject(new Error('Temporary MySQL did not start: ' + dbLog));
        else setTimeout(probe, 100);
      });
    };
    probe();
  });
  console.log((await run('php', [path.join(sourceDir, 'seed.php'), '--verify-server'], fixtureEnv)).trim());
  if (process.argv.includes('--integration')) {
    console.log((await run('php', ['tests/run.php', '--integration'], fixtureEnv)).trim().split('\n').at(-1));
  }
  await new Promise((resolve, reject) => {
    const guard = net.createServer(); guard.once('error', reject);
    guard.listen(18765, '127.0.0.1', () => guard.close(resolve));
  });
  await run('php', [path.join(sourceDir, 'seed.php')], fixtureEnv);
  const port = await new Promise((resolve, reject) => {
    const server = net.createServer();
    server.once("error", reject);
    server.listen(0, "127.0.0.1", () => { const port = server.address().port; server.close(() => resolve(port)); });
  });
  const base = `http://127.0.0.1:${port}`;
  php = spawn("php", ["-S", `127.0.0.1:${port}`, "-t", path.join(root, "public")], { cwd: root, windowsHide: true, env: fixtureEnv, stdio: ["ignore", "ignore", "pipe"] });
  await new Promise((resolve, reject) => {
    php.once("error", reject);
    php.stderr.on("data", chunk => { if (chunk.toString().includes("started")) resolve(); });
    php.once("exit", code => reject(new Error(`PHP exited: ${code}`)));
  });
  chrome = spawn("C:/Program Files/Google/Chrome/Application/chrome.exe", ["--headless=new", "--disable-gpu", "--no-first-run", "--no-default-browser-check", "--remote-debugging-port=0", `--user-data-dir=${profileDir}`, "about:blank"], { windowsHide: true, stdio: ["ignore", "ignore", "pipe"] });
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
    if (message.method === 'Page.javascriptDialogOpening' && message.params.type === 'beforeunload' && allowDiscard) {
      send('Page.handleJavaScriptDialog', { accept: true }).catch(() => {});
    }
    if (message.method === 'Fetch.requestPaused') {
      const params = message.params;
      if (new URL(params.request.url).hostname === 'tile.openstreetmap.org') {
        const action = failTiles
          ? send('Fetch.failRequest', { requestId: params.requestId, errorReason: 'ConnectionFailed' })
          : send('Fetch.fulfillRequest', { requestId: params.requestId, responseCode: 200, responseHeaders: [{ name: 'Content-Type', value: 'image/png' }], body: 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aWQAAAABJRU5ErkJggg==' });
        action.catch(() => {});
      } else if (new URL(params.request.url).pathname === '/assets/vendor/leaflet-1.9.4/leaflet.js') {
        (failMapLibrary ? send('Fetch.failRequest', { requestId: params.requestId, errorReason: 'ConnectionFailed' }) : send('Fetch.continueRequest', { requestId: params.requestId })).catch(() => {});
      } else if (new URL(params.request.url).pathname === chartUrl) {
        const action = failLibrary
          ? send('Fetch.failRequest', { requestId: params.requestId, errorReason: 'ConnectionFailed' })
          : send('Fetch.continueRequest', { requestId: params.requestId });
        action.catch(() => {});
      } else if (params.request.url.startsWith('https://')) {
        send('Fetch.failRequest', { requestId: params.requestId, errorReason: 'BlockedByClient' }).catch(() => {});
      } else if (holdHistory) heldRequests.push(params.requestId);
      else send('Fetch.continueRequest', { requestId: params.requestId }).catch(() => {});
    }
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
  await send("Network.setCacheDisabled", { cacheDisabled: true });
  await send('Network.setBlockedURLs', { urls: externalBlocks });
  await send("Fetch.enable", { patterns: [{ urlPattern: 'https://*', requestStage: 'Request' }, { urlPattern: '*/assets/vendor/leaflet-1.9.4/leaflet.js', requestStage: 'Request' }, { urlPattern: '*' + chartUrl, requestStage: "Request" }, { urlPattern: "*api/device-history.php*", requestStage: "Request" }] });
  await send('Page.addScriptToEvaluateOnNewDocument', { source: `document.addEventListener('load', event => {
    if (event.target.tagName === 'SCRIPT' && event.target.src.endsWith('/leaflet.js') && window.L) {
      L.Map.addInitHook(function () { window.fixtureMap = this; });
    }
  }, true);` });
  async function navigate(route) {
    allowDiscard = true;
    const load = loaded();
    await send("Page.navigate", { url: base + route });
    await load;
    allowDiscard = false;
  }

  let cookie = '';
  async function req(route, body) {
    const response = await fetch(base + route, { method: body ? 'POST' : 'GET', redirect: 'manual', headers: { ...(cookie ? {Cookie:cookie} : {}), ...(body ? {'Content-Type':'application/x-www-form-urlencoded'} : {}) }, body:body ? new URLSearchParams(body) : undefined });
    for (const item of response.headers.getSetCookie()) {
      const pair=item.split(';')[0];
      const name=pair.split('=')[0];
      cookie=cookie.split('; ').filter(value=>value && !value.startsWith(name+'=')).concat(pair).join('; ');
    }
    return {code:response.status,location:response.headers.get('location'),html:await response.text()};
  }
  const token = html => html.match(/name="csrf" value="([a-f0-9]+)"/)[1];
  async function checkPopupZoomOut() {
    await send('Input.dispatchMouseEvent', {type:'mouseMoved', x:0, y:0});
    const button = await evaluate(`(() => {
      const view = document.querySelector('.leaflet-popup-content a.btn-primary');
      const zoom = document.querySelector('.leaflet-popup-content button.btn-primary');
      const left = view.getBoundingClientRect(), right = zoom.getBoundingClientRect();
      return {label:zoom.textContent, color:getComputedStyle(zoom).color, viewColor:getComputedStyle(view).color,
        beside:right.left >= left.right && Math.abs(right.top-left.top)<2,
        x:right.left+right.width/2, y:right.top+right.height/2};
    })()`);
    assert.equal(button.label, 'Zoom out');
    assert.equal(button.color, 'rgb(255, 255, 255)');
    assert.equal(button.viewColor, 'rgb(255, 255, 255)');
    assert.equal(button.beside, true);
    await send('Input.dispatchMouseEvent', {type:'mouseMoved', x:button.x, y:button.y});
    await waitFor('getComputedStyle(document.querySelector(".leaflet-popup-content button.btn-primary")).color === "rgb(8, 8, 8)"');
    await evaluate('document.querySelector(".leaflet-popup-content button.btn-primary").click()');
    await waitFor('document.querySelector(".leaflet-control-zoom-out").classList.contains("leaflet-disabled")');
    await evaluate('document.querySelector("[data-map-device]").click()');
    await waitFor('!!document.querySelector(".leaflet-popup-content")');
    await send('Input.dispatchMouseEvent', {type:'mouseMoved', x:0, y:0});
  }
  for (const route of ['/','/devices.php','/maps.php','/logs.php','/firmwares.php','/device-edit.php','/device-history.php?device_id=ESP32-001']) assert.equal((await req(route)).code,302,route);
  for (const route of ['/api/dashboard.php','/api/device-history.php?device_id=ESP32-001']) assert.equal((await req(route)).code,401);
  assert.equal((await req('/api/device-config.php?device_id=ESP32-001')).code,200);
  for (const route of ['/register.php','/verify-email.php','/resend-verification.php']) assert.equal((await req(route)).code,404);
  const snapshot = () => run('php', [path.join(sourceDir, 'seed.php'), '--snapshot'], fixtureEnv);
  const beforeViewer = await snapshot();
  for (const route of ['/viewer.php', '/viewer-maps.php']) {
    const page = await req(route);
    assert.equal(page.code, 200);
    assert.ok(!/href="\/(?:device-edit|device-history|devices|logs|firmwares)\.php/.test(page.html));
    assert.ok(!page.html.includes('Saved logs'));
    assert.equal((await req(route, {device_id:'ESP32-001', water_level:999})).code, 405);
  }
  const viewerData = JSON.parse((await req('/api/viewer-dashboard.php')).html);
  assert.equal(viewerData.online, viewerData.total);
  assert.ok(!('logs' in viewerData));
  assert.ok(!/Offline|device-history.php|device-edit.php/.test(viewerData.html));
  assert.ok(viewerData.html.includes('Online'));
  assert.equal((await req('/api/viewer-dashboard.php',{water_level:999})).code,405);
  for (const route of ['/device-edit.php?id=1&viewer=1', '/firmwares.php?viewer=1', '/device-history.php?device_id=ESP32-001&viewer=1']) {
    assert.equal((await req(route, {device_id:'ESP32-001', water_level:999})).code,302);
  }
  assert.equal((await req('/api/device-history.php?device_id=ESP32-001&viewer=1')).code,401);
  assert.equal(await snapshot(), beforeViewer, 'Viewer and denied writes must not change business data');
  let login=await req('/login.php');
  assert.equal((await req('/login.php',{csrf:'bad',email:'admin@example.test',password:'StrongPassword123!'})).code,422);
  assert.equal((await req('/login.php',{csrf:token(login.html),email:'admin@example.test',password:'wrong'})).code,422);
  assert.equal((await req('/login.php',{csrf:token(login.html),email:'ADMIN@example.test',password:'StrongPassword123!'})).location,'/');
  assert.ok(!(await req('/')).html.includes('Saved logs'));
  const adminData = JSON.parse((await req('/api/dashboard.php')).html);
  assert.ok(adminData.online < adminData.total, 'Admin must retain actual offline statuses');
  assert.ok(adminData.html.includes('device-history.php'));
  for (const route of ['/','/devices.php','/maps.php','/logs.php','/firmwares.php','/api/dashboard.php','/api/device-history.php?device_id=ESP32-001']) assert.equal((await req(route)).code,200,route);
  assert.equal((await req('/logout.php',{csrf:'bad'})).code,422);
  assert.equal((await req('/logout.php',{csrf:token((await req('/')).html)})).location,'/login.php');
  assert.equal((await req('/api/dashboard.php')).code,401);
  for (const width of [390,1440]) {
    await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<768});
    await navigate('/viewer.php');
    assert.equal(await evaluate('document.querySelectorAll("nav a").length'), 2);
    assert.equal(await evaluate('!!document.querySelector("a[href*=device-history], #stat-logs, form")'),false);
    await waitFor('document.querySelector("#refresh-status").textContent.startsWith("Updated ")');
    assert.equal(await evaluate('document.querySelector("#stat-online").textContent === document.querySelector("#stat-total").textContent'),true);
    assert.equal(await evaluate('document.querySelector("#stat-offline").textContent'), '0');
    assert.equal(await evaluate('document.documentElement.scrollWidth > innerWidth + 1'),false);
    await writeFile(path.join(taskDir,'screenshots','viewer-'+width+'.png'),Buffer.from((await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true})).data,'base64'));
    await navigate('/viewer-maps.php');
    await waitFor('!document.querySelector("[data-map-device]").disabled');
    await evaluate('document.querySelector("[data-map-device]").click()');
    await waitFor('!!document.querySelector(".leaflet-popup-content")');
    assert.equal(await evaluate('!!document.querySelector("a[href*=device-edit]")'),false);
    assert.equal(await evaluate('document.querySelector(".leaflet-popup-content .badge").textContent'), 'Online');
    await checkPopupZoomOut();
    assert.equal(await evaluate('document.querySelector(".leaflet-popup-content .btn-primary").textContent'), 'View');
    const viewerView = loaded();
    await evaluate('document.querySelector(".leaflet-popup-content .btn-primary").click()');
    await viewerView;
    assert.equal(await evaluate('location.pathname'), '/viewer.php');
    assert.equal(await evaluate('document.documentElement.scrollWidth > innerWidth + 1'),false);
    await navigate('/login.php');
    assert.equal(await evaluate(`document.documentElement.scrollWidth > innerWidth+1`),false);
    await evaluate(`document.querySelector('#email').value='admin@example.test';document.querySelector('#password').value='StrongPassword123!';`);
    const done=loaded();await evaluate(`document.querySelector('form').requestSubmit()`);await done;
    assert.equal(await evaluate('location.pathname'),'/');
    await navigate('/maps.php');
    await waitFor('!document.querySelector("[data-map-device]").disabled');
    await evaluate('document.querySelector("[data-map-device]").click()');
    await waitFor('!!document.querySelector(".leaflet-popup-content")');
    assert.equal(await evaluate('!!document.querySelector(".leaflet-popup-content a[href*=device-edit]")'), true);
    await checkPopupZoomOut();
    const adminView = loaded();
    await evaluate('document.querySelector(".leaflet-popup-content .btn-primary").click()');
    await adminView;
    assert.equal(await evaluate('location.pathname'), '/');
    const shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});
    await writeFile(path.join(taskDir,'screenshots','login-'+width+'.png'),Buffer.from(shot.data,'base64'));
    const out=loaded();await evaluate(`document.querySelector('form[action="/logout.php"]').requestSubmit()`);await out;
  }
  console.log('Authentication integration and browser checks passed.');
} finally {
  if (socket?.readyState === WebSocket.OPEN) {
    try { await send('Browser.close', {}, null); } catch {}
    socket.close();
  }
  chrome?.kill();
  php?.kill();
  if (mysql) {
    try { await run('C:/develop/web/mysql/bin/mysqladmin.exe', ['--no-defaults', '--protocol=TCP', '--host=127.0.0.1', '--port=' + fixtureEnv.DB_PORT, '--user=root', 'shutdown']); } catch { mysql.kill(); }
    await new Promise(resolve => { if (mysql.exitCode !== null) resolve(); else { mysql.once('exit', resolve); setTimeout(resolve, 5000); } });
  }
  for (const folder of [dataDir, profileDir]) {
    if (path.dirname(path.resolve(folder)) !== taskDir || !path.basename(folder).endsWith(runId)) throw new Error('Unexpected cleanup path');
    await rm(folder, { recursive: true, force: true, maxRetries: 5, retryDelay: 200 });
  }
}
