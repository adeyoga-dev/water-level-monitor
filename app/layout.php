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
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
        <link rel="stylesheet" href="/assets/app.css">
    </head>

    <body>
        <aside class="sidebar"><a class="brand" href="/">≈ <span>flow<span class="brand-dot">.</span></span></a>
            <div class="workspace">WATER LEVEL MONITOR</div>
            <nav><?php foreach (['dashboard' => ['/', '◫', 'Dashboard'], 'devices' => ['/devices.php', '▤', 'Devices'], 'logs' => ['/logs.php', '≡', 'Sensor logs'], 'firmwares' => ['/firmwares.php', '↑', 'OTA firmware']] as $key => $item): ?><a class="<?= $active === $key ? 'active' : '' ?>" href="<?= $item[0] ?>"><span><?= $item[1] ?></span><?= $item[2] ?></a><?php endforeach ?></nav>
            <div class="sidebar-footer"><span class="live-dot"></span> Water level monitoring<br><small>ESP32 · <?= h(config('APP_TIMEZONE', 'Asia/Jakarta')) ?></small></div>
        </aside>
        <main>
            <header><span>Monitoring system / <b><?= h($title) ?></b></span><span class="header-tag">● &nbsp; ADMIN PANEL</span></header>
            <section class="content"><?php if ($flash): ?><div class="notice success"><?= h($flash) ?></div><?php endif ?>
            <?php }
        function pageEnd(): void
        { ?></section>
            <footer>Flow · Water level monitoring <span>PHP & ESP32</span></footer>
        </main>
    </body>

    </html><?php }
        function pageTitle(string $eyebrow, string $title, string $description): void
        { ?><div class="page-heading">
        <div>
            <div class="eyebrow"><?= h($eyebrow) ?></div>
            <h1><?= h($title) ?></h1>
            <p><?= h($description) ?></p>
        </div>
    </div><?php }
        function deviceTable(array $devices): void
        { ?>
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>DEVICE</th>
                    <th>STATUS</th>
                    <th>WATER LEVEL</th>
                    <th>ALERT LEVEL</th>
                    <th>BUZZER</th>
                    <th>INTERVAL</th>
                    <th>FIRMWARE</th>
                    <th>LAST SEEN</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="device-rows"><?php foreach ($devices as $device): ?><tr>
                        <td><strong><?= h($device['device_id']) ?></strong><small>#<?= h($device['id']) ?></small></td>
                        <td><span class="badge-state <?= $device['device_status'] ? 'online' : 'offline' ?>"><?= $device['device_status'] ? 'Online' : 'Offline' ?></span></td>
                        <td><strong><?= $device['water_level'] === null ? '—' : h(number_format((float)$device['water_level'], 2)) ?></strong> <span class="muted">cm</span></td>
                        <td><span class="alert-label alert-<?= $device['alert_status'] === null ? 'none' : h($device['alert_status']) ?>"><?= h($device['water_level'] === null ? 'No readings yet' : alertLabel($device['alert_status'])) ?></span></td>
                        <td><?= $device['buzzer_status'] ? 'Enabled' : 'Disabled' ?><small>Threshold &gt; <?= h($device['alarm_threshold']) ?> cm</small></td>
                        <td><?= h($device['update_interval']) ?> sec</td>
                        <td><?= h($device['reported_firmware'] ?? '—') ?><small>Target <?= h($device['current_firmware'] ?? '—') ?></small></td>
                        <td><?= h($device['last_seen_display']) ?></td>
                        <td><a class="text-link" href="/device-edit.php?id=<?= h($device['id']) ?>">Edit ↗</a></td>
                    </tr><?php endforeach ?><?php if (!$devices): ?><tr>
                        <td colspan="9" class="empty">No devices yet. Add your first device to start monitoring.</td>
                    </tr><?php endif ?></tbody>
        </table>
    </div>
<?php }
