<?php
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/layout.php';
$filters = [];
$params = [];
$error = null;
$device = is_string($_GET['device_id'] ?? null) ? $_GET['device_id'] : '';
$status = is_string($_GET['alarm_status'] ?? null) ? $_GET['alarm_status'] : '';
$from = is_string($_GET['from'] ?? null) ? $_GET['from'] : '';
$to = is_string($_GET['to'] ?? null) ? $_GET['to'] : '';
if ($device !== '') {
    $filters[] = 'device_id=?';
    $params[] = $device;
}
if ($status === 'none') $filters[] = 'alarm_status IS NULL';
elseif (in_array($status, ['0', '1', '2'], true)) {
    $filters[] = 'alarm_status=?';
    $params[] = $status;
}
foreach (['from' => $from, 'to' => $to] as $key => $date) {
    if ($date === '') continue;
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone(config('APP_TIMEZONE', 'Asia/Jakarta')));
    if (!$parsed || $parsed->format('Y-m-d') !== $date) {
        $error = 'Format tanggal tidak valid.';
        continue;
    }
    $filters[] = $key === 'from' ? 'created_at >= ?' : 'created_at < ?';
    $params[] = ($key === 'to' ? $parsed->modify('+1 day') : $parsed)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}
if ($from !== '' && $to !== '' && $from > $to) $error = 'Tanggal awal tidak boleh melewati tanggal akhir.';
if ($error) $filters[] = '1=0';
$where = $filters ? ' WHERE ' . implode(' AND ', $filters) : '';
$count = (int)query('SELECT COUNT(*) FROM sensor_logs' . $where, $params)->fetchColumn();
$pages = max(1, (int)ceil($count / 25));
$page = min($pages, max(1, (int)($_GET['page'] ?? 1)));
$offset = ($page - 1) * 25;
$logs = query('SELECT * FROM sensor_logs' . $where . ' ORDER BY created_at DESC,id DESC LIMIT 25 OFFSET ' . $offset, $params)->fetchAll();
$devices = query('SELECT device_id FROM devices ORDER BY device_id')->fetchAll();
$link = fn($p) => '?' . http_build_query(['device_id' => $device, 'alarm_status' => $status, 'from' => $from, 'to' => $to, 'page' => $p]);
pageStart('Catatan sensor', 'logs');
// pageTitle('RIWAYAT SENSOR', 'Catatan sensor', 'Lihat riwayat tinggi air dan klasifikasi siaga saat pembacaan diterima.');
?>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= h(uiText($error)) ?></div><?php endif ?>
<form class="card card-body mb-4" method="get">
    <div class="form-row align-items-end">
        <div class="form-group col-sm-6 col-xl-3">
            <label for="filter-device">Perangkat</label>
            <select class="form-control" id="filter-device" name="device_id">
                <option value="">Semua perangkat</option>
                <?php foreach ($devices as $d): ?><option <?= $device === $d['device_id'] ? 'selected' : '' ?> value="<?= h($d['device_id']) ?>"><?= h($d['device_id']) ?></option><?php endforeach ?>
            </select>
        </div>
        <div class="form-group col-sm-6 col-xl-3">
            <label for="filter-alert">Status siaga</label>
            <select class="form-control" id="filter-alert" name="alarm_status">
                <option value="">Semua status</option>
                <?php foreach (['0' => 'Siaga 1', '1' => 'Siaga 2', '2' => 'Siaga 3', 'none' => 'Tidak terklasifikasi'] as $key => $label): ?><option value="<?= h($key) ?>" <?= $status === (string)$key ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach ?>
            </select>
        </div>
        <div class="form-group col-sm-6 col-xl-3">
            <label for="filter-from">Tanggal awal</label>
            <input class="form-control" id="filter-from" type="date" name="from" value="<?= h($from) ?>">
        </div>
        <div class="form-group col-sm-6 col-xl-3">
            <label for="filter-to">Tanggal akhir</label>
            <input class="form-control" id="filter-to" type="date" name="to" value="<?= h($to) ?>">
        </div>
    </div>
    <div><button class="btn btn-primary mr-2" type="submit">Terapkan</button><a class="btn btn-outline-secondary" href="/logs.php">Atur ulang</a></div>
</form>
<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
        <h2 class="h5 mb-0 mr-2">Riwayat pembacaan</h2><span class="badge badge-secondary"><?= $count ?> catatan</span>
    </div>
    <div class="table-responsive" tabindex="0" role="region" aria-label="Riwayat pembacaan sensor">
        <table class="table table-striped table-hover mb-0">
            <thead class="thead-light text-nowrap">
                <tr>
                    <th scope="col">ID catatan</th>
                    <th scope="col">Perangkat</th>
                    <th scope="col">Tinggi air</th>
                    <th scope="col">Status siaga</th>
                    <th scope="col">Waktu (<?= h(config('APP_TIMEZONE', 'Asia/Jakarta')) ?>)</th>
                </tr>
            </thead>
            <tbody><?php foreach ($logs as $log): ?><tr>
                        <td class="text-muted">#<?= h($log['id']) ?></td>
                        <td class="text-nowrap"><strong><?= h($log['device_id']) ?></strong></td>
                        <td class="text-nowrap"><strong><?= h(number_format((float)$log['water_level'], 2)) ?></strong> cm</td>
                        <td><span class="badge badge-<?= [0 => 'success', 1 => 'warning', 2 => 'danger'][$log['alarm_status'] ?? -1] ?? 'secondary' ?>"><?= h(uiText(alertLabel($log['alarm_status']))) ?></span></td>
                        <td class="text-nowrap"><?= h(uiText(displayTime($log['created_at']))) ?></td>
                    </tr><?php endforeach ?><?php if (!$logs): ?><tr>
                        <td colspan="5" class="text-center text-muted py-5">Tidak ada catatan yang sesuai dengan filter.</td>
                    </tr><?php endif ?></tbody>
        </table>
    </div>
    <div class="card-footer d-flex flex-wrap justify-content-between align-items-center">
        <span class="small text-muted mr-2">Halaman <?= $page ?> dari <?= $pages ?></span>
        <nav aria-label="Halaman catatan sensor">
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>">
                    <?php if ($page <= 1): ?><span class="page-link" aria-disabled="true">Sebelumnya</span><?php else: ?><a class="page-link" href="<?= h($link($page - 1)) ?>">Sebelumnya</a><?php endif ?>
                </li>
                <li class="page-item<?= $page >= $pages ? ' disabled' : '' ?>">
                    <?php if ($page >= $pages): ?><span class="page-link" aria-disabled="true">Berikutnya</span><?php else: ?><a class="page-link" href="<?= h($link($page + 1)) ?>">Berikutnya</a><?php endif ?>
                </li>
            </ul>
        </nav>
    </div>
</div>
<?php pageEnd(); ?>
