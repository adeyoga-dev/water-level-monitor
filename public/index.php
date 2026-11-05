<?php
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/admin.php';
require dirname(__DIR__) . '/app/layout.php';
$devices = deviceList();
$online = count(array_filter($devices,fn($d)=>(int)$d['device_status']===1));
$logs = (int) query('SELECT COUNT(*) FROM sensor_logs')->fetchColumn();
pageStart('Dashboard','dashboard');
pageTitle('OVERVIEW','Monitor water. Stay informed.','Device status and water levels, all in one place.');
?>
<div class="overview-banner"><div><span class="live-dot"></span> CONTINUOUS MONITORING<h2>Every change, in view.</h2><p>The latest readings from your ESP32 devices.</p></div><div class="water-art"><i></i><i></i><i></i><span>≈</span></div></div>
<div class="stats"><article><span class="stat-icon">▤</span><div>TOTAL DEVICES<strong id="stat-total"><?= count($devices) ?></strong><small>Registered devices</small></div></article><article><span class="stat-icon green">↗</span><div>ONLINE<strong id="stat-online"><?= $online ?></strong><small>Connected to the server</small></div></article><article><span class="stat-icon gray">○</span><div>OFFLINE<strong id="stat-offline"><?= count($devices)-$online ?></strong><small>Awaiting communication</small></div></article><article><span class="stat-icon amber">≡</span><div>SAVED LOGS<strong id="stat-logs"><?= $logs ?></strong><small>Total sensor readings</small></div></article></div>
<div class="panel"><div class="panel-heading"><div><h2>Your devices</h2><p><span class="live-dot"></span> <span id="refresh-status">Refreshes every 5 seconds</span></p></div><a class="button" href="/device-edit.php">+ Add device</a></div><?php deviceTable($devices) ?></div><script src="/assets/dashboard.js" defer></script>
<?php pageEnd(); ?>
