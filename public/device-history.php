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
    pageStart('Riwayat perangkat', 'dashboard');
    pageTitle('RIWAYAT PERANGKAT', $invalidFilters ? 'Filter tanggal tidak valid' : 'Perangkat tidak ditemukan', $invalidFilters ? uiText($exception->getMessage()) : 'Periksa ID perangkat atau kembali ke dasbor.');
    echo '<a class="btn btn-primary" href="/">Kembali ke dasbor</a>';
    pageEnd();
    exit;
}
pageStart('Riwayat perangkat', 'dashboard');
?>
<a class="d-inline-block mb-3" href="/">← Kembali ke dasbor</a>
<?php
// pageTitle('RIWAYAT PERANGKAT', 'Detail dan riwayat tinggi air', $history['device']['device_id']);
?>
<div class="row">
    <div class="col-xl-6">
        <div class="row" id="history-device"><?php waterLevelPanels([$history['device']], true) ?></div>
    </div>
    <div class="col-xl-6 mb-4">
        <section class="card h-100" aria-labelledby="history-chart-title">
            <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center">
                <h2 class="h5 mb-0 mr-2" id="history-chart-title">Tinggi air trend</h2>
                <button class="btn btn-sm btn-outline-primary my-1" id="history-refresh" type="button">Perbarui</button>
            </div>
            <div class="card-body" id="history-content" aria-busy="true">
                <form id="history-filters" data-today="<?= h($today) ?>" class="mb-3">
                    <div class="form-row">
                        <div class="form-group col-6 col-md-4">
                            <label class="small" for="history-from">Tanggal awal</label>
                            <input class="form-control form-control-sm" id="history-from" name="from" type="date" min="1000-01-01" max="9999-12-30" value="<?= h($history['filters']['from']) ?>" required>
                        </div>
                        <div class="form-group col-6 col-md-4">
                            <label class="small" for="history-to">Tanggal akhir</label>
                            <input class="form-control form-control-sm" id="history-to" name="to" type="date" min="1000-01-01" max="9999-12-30" value="<?= h($history['filters']['to']) ?>" required>
                        </div>
                        <div class="form-group col-12 col-md-4">
                            <label class="small" for="history-group">Kelompokkan</label>
                            <select class="form-control form-control-sm" id="history-group" name="group_by">
                                <?php foreach (['auto' => 'Otomatis', 'minute' => 'Menit', 'hour' => 'Jam', 'day' => 'Hari', 'month' => 'Bulan', 'year' => 'Tahun'] as $value => $label): ?>
                                    <option value="<?= $value ?>" <?= $history['filters']['group_by'] === $value ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button class="btn btn-sm btn-primary" id="history-apply" type="submit">Terapkan filter</button>
                    <button class="btn btn-sm btn-outline-secondary" id="history-reset" type="button">Hari ini</button>
                    <small class="d-block text-muted mt-2" id="history-filter-state" role="status" aria-live="polite"></small>
                </form>
                <p class="small text-muted" id="history-status" role="status" aria-live="polite">Memuat tren tinggi air…</p>
                <div class="alert alert-danger" id="history-error" role="alert" tabindex="-1" hidden></div>
                <p class="text-muted py-4 text-center" id="history-empty" hidden>Tidak ada pembacaan sensor pada rentang tanggal ini.</p>
                <div id="history-chart-area" hidden>
                    <canvas id="history-chart" role="img" aria-label="Tinggi air dalam sentimeter terhadap waktu" aria-describedby="history-summary">Tren tinggi air. Ringkasan teks tersedia di bawah grafik.</canvas>
                </div>
                <p class="small text-muted mt-3 mb-2" id="history-summary"></p>
                <div class="small mb-3" id="history-legend" hidden>
                    Klasifikasi titik:
                    <span class="badge badge-success">Siaga 1</span>
                    <span class="badge badge-warning">Siaga 2</span>
                    <span class="badge badge-danger">Siaga 3</span>
                    <span class="badge badge-secondary">Tidak terklasifikasi</span>
                </div>
                <p class="small text-muted mb-0" id="history-note"></p>
            </div>
        </section>
    </div>
</div>
<script id="history-data" type="application/json">
    <?= json_encode($history, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
</script>
<script src="/assets/vendor/chartjs-4.6.1/chart.umd.min.js" defer></script>
<script src="/assets/history.js" defer></script>
<?php pageEnd(); ?>