<?php
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/admin.php';
require dirname(__DIR__) . '/app/layout.php';
$localToday = new DateTimeImmutable('today', new DateTimeZone(config('APP_TIMEZONE', 'Asia/Jakarta')));
$today = $localToday->format('Y-m-d');
$defaultFrom = $localToday->modify('-29 days')->format('Y-m-d');
$filters = $_GET;
if (!array_key_exists('from', $filters) && !array_key_exists('to', $filters)) {
    $filters += ['from' => $defaultFrom, 'to' => $today];
}
try {
    $history = deviceHistory($_GET['device_id'] ?? null, $filters);
} catch (InvalidArgumentException | OutOfBoundsException $exception) {
    $invalidFilters = $exception instanceof InvalidArgumentException && $exception->getMessage() !== 'Invalid device_id.';
    http_response_code($invalidFilters ? 422 : 404);
    pageStart('Device history', 'dashboard');
    pageTitle('DEVICE HISTORY', $invalidFilters ? 'Invalid date filters' : 'Device not found', $invalidFilters ? $exception->getMessage() : 'Check the device identifier or return to the dashboard.');
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
                <form id="history-filters" data-today="<?= h($today) ?>" class="mb-3">
                    <div class="form-row">
                        <div class="form-group col-6 col-md-4">
                            <label class="small" for="history-from">From date</label>
                            <input class="form-control form-control-sm" id="history-from" name="from" type="date" min="1000-01-01" max="9999-12-30" value="<?= h($history['filters']['from']) ?>" required>
                        </div>
                        <div class="form-group col-6 col-md-4">
                            <label class="small" for="history-to">To date</label>
                            <input class="form-control form-control-sm" id="history-to" name="to" type="date" min="1000-01-01" max="9999-12-30" value="<?= h($history['filters']['to']) ?>" required>
                        </div>
                        <div class="form-group col-12 col-md-4">
                            <label class="small" for="history-group">Group by</label>
                            <select class="form-control form-control-sm" id="history-group" name="group_by" aria-describedby="history-group-help">
                                <?php foreach (['auto' => 'Auto', 'minute' => 'Minute', 'hour' => 'Hour', 'day' => 'Day', 'month' => 'Month', 'year' => 'Year'] as $value => $label): ?>
                                    <option value="<?= $value ?>" <?= $history['filters']['group_by'] === $value ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted" id="history-group-help">Minute: select a single date.</small>
                        </div>
                    </div>
                    <button class="btn btn-sm btn-primary" id="history-apply" type="submit">Apply filters</button>
                    <button class="btn btn-sm btn-outline-secondary" id="history-reset" type="button">Today</button>
                    <small class="d-block text-muted mt-2" id="history-filter-state" role="status" aria-live="polite"></small>
                </form>
                <p class="small text-muted" id="history-status" role="status" aria-live="polite">Loading water level trend…</p>
                <div class="alert alert-danger" id="history-error" role="alert" hidden></div>
                <p class="text-muted py-4 text-center" id="history-empty" hidden>No sensor readings in this date range.</p>
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
                <p class="small text-muted mb-0" id="history-note"></p>
            </div>
        </section>
    </div>
</div>
<script id="history-data" type="application/json"><?= json_encode($history, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="/assets/vendor/chartjs-4.6.1/chart.umd.min.js" defer></script>
<script src="/assets/history.js" defer></script>
<?php pageEnd(); ?>
