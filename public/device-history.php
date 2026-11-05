<?php
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/admin.php';
require dirname(__DIR__) . '/app/layout.php';
try {
    $history = deviceHistory($_GET['device_id'] ?? null);
} catch (InvalidArgumentException | OutOfBoundsException) {
    http_response_code(404);
    pageStart('Device history', 'dashboard');
    pageTitle('DEVICE HISTORY', 'Device not found', 'Check the device identifier or return to the dashboard.');
    echo '<a class="btn btn-primary" href="/">Back to dashboard</a>';
    pageEnd();
    exit;
}
pageStart('Device history', 'dashboard');
?>
<a class="d-inline-block mb-3" href="/">← Back to dashboard</a>
<?php pageTitle('DEVICE HISTORY', 'Water level details & history', $history['device']['device_id']); ?>
<div class="row">
    <div class="col-xl-6">
        <div class="row" id="history-device"><?php waterLevelPanels([$history['device']], true) ?></div>
    </div>
    <div class="col-xl-6 mb-4">
        <section class="card h-100" aria-labelledby="history-chart-title">
            <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center">
                <h2 class="h5 mb-0 mr-2" id="history-chart-title">Water level trend</h2>
                <button class="btn btn-sm btn-outline-primary my-1" id="history-refresh" type="button">Refresh</button>
            </div>
            <div class="card-body" id="history-content" aria-busy="true">
                <p class="small text-muted" id="history-status" role="status" aria-live="polite">Loading water level trend…</p>
                <div class="alert alert-danger" id="history-error" role="alert" hidden></div>
                <p class="text-muted py-4 text-center" id="history-empty" hidden>No sensor readings have been recorded for this device.</p>
                <div id="history-chart-area" hidden>
                    <canvas id="history-chart" role="img" aria-label="Water level in centimetres over time" aria-describedby="history-summary">Water level trend. A text summary is provided below the chart.</canvas>
                </div>
                <p class="small text-muted mt-3 mb-2" id="history-summary"></p>
                <div class="small mb-3" id="history-legend" hidden>
                    Point classifications:
                    <span class="badge badge-success">Siaga 1</span>
                    <span class="badge badge-warning">Siaga 2</span>
                    <span class="badge badge-danger">Siaga 3</span>
                    <span class="badge badge-secondary">Unclassified</span>
                </div>
                <p class="small text-muted mb-0">Latest <?= $history['limit'] ?> readings, ordered by time. Times use <?= h($history['timezone']) ?>. Refreshes every 5 seconds.</p>
            </div>
        </section>
    </div>
</div>
<script id="history-data" type="application/json"><?= json_encode($history, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="/assets/vendor/chartjs-4.6.1/chart.umd.min.js" defer></script>
<script src="/assets/history.js" defer></script>
<?php pageEnd(); ?>
