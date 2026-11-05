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
        $error = 'Invalid date format.';
        continue;
    }
    $filters[] = $key === 'from' ? 'created_at >= ?' : 'created_at < ?';
    $params[] = ($key === 'to' ? $parsed->modify('+1 day') : $parsed)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}
if ($from !== '' && $to !== '' && $from > $to) $error = 'The start date must be on or before the end date.';
if ($error) $filters[] = '1=0';
$where = $filters ? ' WHERE ' . implode(' AND ', $filters) : '';
$count = (int)query('SELECT COUNT(*) FROM sensor_logs' . $where, $params)->fetchColumn();
$pages = max(1, (int)ceil($count / 25));
$page = min($pages, max(1, (int)($_GET['page'] ?? 1)));
$offset = ($page - 1) * 25;
$logs = query('SELECT * FROM sensor_logs' . $where . ' ORDER BY created_at DESC,id DESC LIMIT 25 OFFSET ' . $offset, $params)->fetchAll();
$devices = query('SELECT device_id FROM devices ORDER BY device_id')->fetchAll();
$link = fn($p) => '?' . http_build_query(['device_id' => $device, 'alarm_status' => $status, 'from' => $from, 'to' => $to, 'page' => $p]);
pageStart('Sensor logs', 'logs');
pageTitle('SENSOR HISTORY', 'Sensor logs', 'Review water level history and alert classifications recorded when readings were received.');
?>
<?php if ($error): ?><div class="notice error"><?= h($error) ?></div><?php endif ?>
<form class="panel filters" method="get"><label>Devices<select name="device_id">
            <option value="">All devices</option><?php foreach ($devices as $d): ?><option <?= $device === $d['device_id'] ? 'selected' : '' ?> value="<?= h($d['device_id']) ?>"><?= h($d['device_id']) ?></option><?php endforeach ?>
        </select></label><label>Alert level<select name="alarm_status">
            <option value="">All levels</option><?php foreach (['0' => 'Siaga 1', '1' => 'Siaga 2', '2' => 'Siaga 3', 'none' => 'Unclassified'] as $key => $label): ?><option value="<?= h($key) ?>" <?= $status === (string)$key ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach ?>
        </select></label><label>From date<input type="date" name="from" value="<?= h($from) ?>"></label><label>To date<input type="date" name="to" value="<?= h($to) ?>"></label><button class="button">Apply</button><a class="text-link" href="/logs.php">Reset</a></form>
<div class="panel">
    <div class="panel-heading">
        <h2>Reading history</h2><span class="count-pill"><?= $count ?> logs</span>
    </div>
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>LOG ID</th>
                    <th>DEVICE</th>
                    <th>WATER LEVEL</th>
                    <th>ALERT LEVEL</th>
                    <th>TIME (<?= h(config('APP_TIMEZONE', 'Asia/Jakarta')) ?>)</th>
                </tr>
            </thead>
            <tbody><?php foreach ($logs as $log): ?><tr>
                        <td class="muted">#<?= h($log['id']) ?></td>
                        <td><strong><?= h($log['device_id']) ?></strong></td>
                        <td><strong><?= h(number_format((float)$log['water_level'], 2)) ?></strong> cm</td>
                        <td><span class="alert-label alert-<?= $log['alarm_status'] === null ? 'none' : h($log['alarm_status']) ?>"><?= h(alertLabel($log['alarm_status'])) ?></span></td>
                        <td><?= h(displayTime($log['created_at'])) ?></td>
                    </tr><?php endforeach ?><?php if (!$logs): ?><tr>
                        <td colspan="5" class="empty">No logs match your filters.</td>
                    </tr><?php endif ?></tbody>
        </table>
    </div>
    <div class="pagination-row"><a href="<?= h($link(max(1, $page - 1))) ?>">← Previous</a><span>Page <?= $page ?> of <?= $pages ?></span><a href="<?= h($link(min($pages, $page + 1))) ?>">Next →</a></div>
</div>
<?php pageEnd(); ?>