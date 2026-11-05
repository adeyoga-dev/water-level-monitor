<?php
function pageStart(string $title, string $active): void
{
    startSession();
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?> · Flow</title>
    <link rel="stylesheet" href="/assets/vendor/bootstrap-4.6.2-dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
    <div class="container-fluid">
        <div class="row min-vh-100">
            <aside class="col-md-3 col-lg-2 bg-white border-right p-3">
                <a class="h3 d-block mb-1 text-primary text-decoration-none" href="/">Flow</a>
                <p class="small text-muted mb-4">Water level monitor</p>
                <nav class="nav flex-column nav-pills" aria-label="Main navigation">
                    <?php foreach (['dashboard' => ['/', 'Dashboard'], 'devices' => ['/devices.php', 'Devices'], 'logs' => ['/logs.php', 'Sensor logs'], 'firmwares' => ['/firmwares.php', 'OTA firmware']] as $key => $item): ?>
                        <a class="nav-link mb-1<?= $active === $key ? ' active' : '' ?>" href="<?= $item[0] ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= $item[1] ?></a>
                    <?php endforeach ?>
                </nav>
                <p class="small text-muted border-top pt-3 mt-4 mb-0">ESP32 monitoring<br><?= h(config('APP_TIMEZONE', 'Asia/Jakarta')) ?></p>
            </aside>
            <div class="col-md-9 col-lg-10 d-flex flex-column px-0">
                <header class="bg-white border-bottom px-3 px-lg-4 py-3 d-flex flex-wrap justify-content-between align-items-center">
                    <span class="text-muted">Monitoring system / <strong class="text-dark"><?= h($title) ?></strong></span>
                    <span class="badge badge-primary">Admin panel</span>
                </header>
                <main class="p-3 p-lg-4 flex-grow-1">
                    <?php if ($flash): ?><div class="alert alert-success" role="status"><?= h($flash) ?></div><?php endif ?>
<?php
}

function pageEnd(): void
{
?>
                </main>
                <footer class="border-top px-3 px-lg-4 py-3 d-flex flex-wrap justify-content-between small text-muted">
                    <span>Flow · Water level monitoring</span><span>PHP &amp; ESP32</span>
                </footer>
            </div>
        </div>
    </div>
</body>
</html>
<?php
}

function pageTitle(string $eyebrow, string $title, string $description): void
{
?>
    <div class="mb-4">
        <p class="small text-primary font-weight-bold mb-1"><?= h($eyebrow) ?></p>
        <h1 class="h3"><?= h($title) ?></h1>
        <p class="text-muted mb-0"><?= h($description) ?></p>
    </div>
<?php
}

function deviceTable(array $devices): void
{
?>
    <div class="table-responsive" tabindex="0" role="region" aria-label="Device readings">
        <table class="table table-striped table-hover mb-0">
            <thead class="thead-light text-nowrap">
                <tr>
                    <th scope="col">Device</th>
                    <th scope="col">Status</th>
                    <th scope="col">Water level</th>
                    <th scope="col">Alert level</th>
                    <th scope="col">Buzzer</th>
                    <th scope="col">Interval</th>
                    <th scope="col">Firmware</th>
                    <th scope="col">Last seen</th>
                    <th scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody id="device-rows">
                <?php foreach ($devices as $device): ?>
                    <tr>
                        <td class="text-nowrap"><strong><?= h($device['device_id']) ?></strong><small class="d-block text-muted">#<?= h($device['id']) ?></small></td>
                        <td><span class="badge badge-<?= $device['device_status'] ? 'success' : 'secondary' ?>"><?= $device['device_status'] ? 'Online' : 'Offline' ?></span></td>
                        <td class="text-nowrap"><strong><?= $device['water_level'] === null ? '—' : h(number_format((float)$device['water_level'], 2)) ?></strong> <span class="text-muted">cm</span></td>
                        <td><span class="badge badge-<?= [0 => 'success', 1 => 'warning', 2 => 'danger'][$device['alert_status'] ?? -1] ?? 'secondary' ?>"><?= h($device['water_level'] === null ? 'No readings yet' : alertLabel($device['alert_status'])) ?></span></td>
                        <td class="text-nowrap"><?= $device['buzzer_status'] ? 'Enabled' : 'Disabled' ?><small class="d-block text-muted">Threshold &gt; <?= h($device['alarm_threshold']) ?> cm</small></td>
                        <td class="text-nowrap"><?= h($device['update_interval']) ?> sec</td>
                        <td class="text-nowrap"><?= h($device['reported_firmware'] ?? '—') ?><small class="d-block text-muted">Target <?= h($device['current_firmware'] ?? '—') ?></small></td>
                        <td class="text-nowrap"><?= h($device['last_seen_display']) ?></td>
                        <td><a class="btn btn-sm btn-outline-primary" href="/device-edit.php?id=<?= h($device['id']) ?>">Edit<span class="sr-only"> <?= h($device['device_id']) ?></span></a></td>
                    </tr>
                <?php endforeach ?>
                <?php if (!$devices): ?>
                    <tr><td colspan="9" class="text-center text-muted py-5">No devices yet. Add your first device to start monitoring.</td></tr>
                <?php endif ?>
            </tbody>
        </table>
    </div>
<?php
}
