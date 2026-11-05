<?php
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/admin.php';
require dirname(__DIR__) . '/app/layout.php';
$devices = deviceList();
$online = count(array_filter($devices, fn($d) => (int)$d['device_status'] === 1));
$logs = (int) query('SELECT COUNT(*) FROM sensor_logs')->fetchColumn();
pageStart('Dashboard', 'dashboard');
pageTitle('OVERVIEW', 'Monitor water. Stay informed.', 'Device status and water levels, all in one place.');
?>
<div class="row">
    <div class="col-sm-6 col-xl-3 mb-4">
        <article class="card h-100 border-primary">
            <div class="card-body">
                <h2 class="h6 text-muted">Total devices</h2>
                <strong class="h2 d-block text-primary" id="stat-total"><?= count($devices) ?></strong>
                <small class="text-muted">Registered devices</small>
            </div>
        </article>
    </div>
    <div class="col-sm-6 col-xl-3 mb-4">
        <article class="card h-100 border-success">
            <div class="card-body">
                <h2 class="h6 text-muted">Online</h2>
                <strong class="h2 d-block text-success" id="stat-online"><?= $online ?></strong>
                <small class="text-muted">Connected to the server</small>
            </div>
        </article>
    </div>
    <div class="col-sm-6 col-xl-3 mb-4">
        <article class="card h-100 border-secondary">
            <div class="card-body">
                <h2 class="h6 text-muted">Offline</h2>
                <strong class="h2 d-block text-secondary" id="stat-offline"><?= count($devices) - $online ?></strong>
                <small class="text-muted">Awaiting communication</small>
            </div>
        </article>
    </div>
    <div class="col-sm-6 col-xl-3 mb-4">
        <article class="card h-100 border-info">
            <div class="card-body">
                <h2 class="h6 text-muted">Saved logs</h2>
                <strong class="h2 d-block text-info" id="stat-logs"><?= $logs ?></strong>
                <small class="text-muted">Total sensor readings</small>
            </div>
        </article>
    </div>
</div>
<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
        <div class="mr-3">
            <h2 class="h5 mb-1">Your devices</h2>
            <p class="small text-muted mb-0" id="refresh-status" role="status" aria-live="polite">Refreshes every 5 seconds</p>
        </div>
        <a class="btn btn-primary my-1" href="/device-edit.php">Add device</a>
    </div>
    <div id="device-table" aria-busy="false"><?php deviceTable($devices) ?></div>
</div>
<script src="/assets/dashboard.js" defer></script>
<?php pageEnd(); ?>
