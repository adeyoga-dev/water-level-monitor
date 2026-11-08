<?php
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/admin.php';
require dirname(__DIR__) . '/app/layout.php';
$viewer = defined('FLOW_VIEWER') && FLOW_VIEWER;
$devices = deviceList(!$viewer);
$online = $viewer ? count($devices) : count(array_filter($devices, fn($d) => (int)$d['device_status'] === 1));
pageStart('Dashboard', 'dashboard', $viewer);
pageTitle('OVERVIEW', 'Monitor water. Stay informed.', 'Device status and water levels, all in one place.');
?>
<div class="flow-stats row mx-n2 mb-2">
    <div class="col-md-6 col-lg-4 px-2 mb-3">
        <article class="card h-100 shadow-sm">
            <div class="card-body d-flex align-items-center p-3">
                <span class="flow-stat-icon flow-stat-total d-flex align-items-center justify-content-center flex-shrink-0 rounded bg-light text-primary p-3 mr-3" aria-hidden="true">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                        <path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z" />
                        <circle cx="12" cy="10" r="3" />
                    </svg>
                </span>
                <div>
                    <h2 class="h6 font-weight-normal text-muted mb-1">Total devices</h2>
                    <strong class="h4 d-block mb-0" id="stat-total"><?= count($devices) ?></strong>
                </div>
            </div>
        </article>
    </div>
    <div class="col-md-6 col-lg-4 px-2 mb-3">
        <article class="card h-100 shadow-sm">
            <div class="card-body d-flex align-items-center p-3">
                <span class="flow-stat-icon flow-stat-online d-flex align-items-center justify-content-center flex-shrink-0 rounded bg-light text-success p-3 mr-3" aria-hidden="true">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                        <path d="M4 20h3v-6H4Zm6 0h3V9h-3Zm6 0h3V4h-3Z" />
                    </svg>
                </span>
                <div>
                    <h2 class="h6 font-weight-normal text-muted mb-1">Online</h2>
                    <strong class="h4 d-block text-success mb-0" id="stat-online"><?= $online ?></strong>
                </div>
            </div>
        </article>
    </div>
    <div class="col-md-6 col-lg-4 px-2 mb-3">
        <article class="card h-100 shadow-sm">
            <div class="card-body d-flex align-items-center p-3">
                <span class="flow-stat-icon flow-stat-offline d-flex align-items-center justify-content-center flex-shrink-0 rounded bg-light text-danger p-3 mr-3" aria-hidden="true">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                        <circle cx="12" cy="12" r="9" />
                        <path d="m6 6 12 12M9 5h6v14H9Z" />
                    </svg>
                </span>
                <div>
                    <h2 class="h6 font-weight-normal text-muted mb-1">Offline</h2>
                    <strong class="h4 d-block text-danger mb-0" id="stat-offline"><?= count($devices) - $online ?></strong>
                </div>
            </div>
        </article>
    </div>
</div>
<p class="small text-muted" id="refresh-status" role="status" aria-live="polite">Refreshes every 5 seconds</p>
<div class="row" id="water-level-panels" data-endpoint="<?= $viewer ? '/api/viewer-dashboard.php' : '/api/dashboard.php' ?>" aria-busy="false"><?php waterLevelPanels($devices, false, $viewer) ?></div>
<script src="/assets/dashboard.js" defer></script>
<?php pageEnd(); ?>