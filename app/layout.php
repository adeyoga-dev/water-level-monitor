<?php
require_once __DIR__ . '/ui-text.php';
function pageStart(string $title, string $active, bool $viewer = false): void
{
    $navigation = $viewer
        ? ['dashboard' => ['/viewer.php', 'Dasbor'], 'maps' => ['/viewer-maps.php', 'Peta']]
        : ['dashboard' => ['/', 'Dasbor'], 'devices' => ['/devices.php', 'Perangkat'], 'maps' => ['/maps.php', 'Peta'], 'logs' => ['/logs.php', 'Catatan sensor'], 'firmwares' => ['/firmwares.php', 'Firmware OTA']];
    startSession();
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
?>
    <!doctype html>
    <html lang="id">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= h($title) ?> · SIPATA</title>
        <link rel="icon" type="image/svg+xml" sizes="any" href="/assets/images/flow-mark-blue.svg">
        <link rel="stylesheet" href="/assets/vendor/bootstrap-4.6.2-dist/css/bootstrap.min.css">
        <link rel="stylesheet" href="/assets/vendor/fontawesome-free-6.7.2/css/fontawesome.min.css">
        <link rel="stylesheet" href="/assets/vendor/fontawesome-free-6.7.2/css/solid.min.css">
        <script src="/assets/button-icons.js" defer></script>
        <?php if ($active === 'maps'): ?>
            <link rel="stylesheet" href="/assets/vendor/leaflet-1.9.4/leaflet.css"><?php endif ?>
        <link rel="stylesheet" href="/assets/css/design-tokens.css">
        <link rel="stylesheet" href="/assets/css/theme.css">
    </head>

    <body class="bg-light">
        <div class="container-fluid">
            <div class="row min-vh-100">
                <?php if (!$viewer): ?>
                    <aside class="flow-sidebar col-md-3 col-lg-2 bg-white border-right p-3">
                        <a class="flow-logo h3 d-flex align-items-center mb-1 text-primary text-decoration-none" href="<?= $viewer ? '/viewer.php' : '/' ?>" aria-label="Flow — Dasbor">
                            <img class="flow-brand-mark" src="/assets/images/flow-mark-blue.svg" width="36" height="36" alt="" aria-hidden="true">
                            <span>SIPATA</span>
                        </a>
                        <p class="small text-muted mb-4">Sistem Pemantauan Tinggi Air</p>
                        <details class="flow-navigation">
                            <summary><span aria-hidden="true">☰</span> Menu</summary>
                            <nav class="nav flex-column nav-pills" aria-label="Navigasi utama">
                                <?php foreach ($navigation as $key => $item): ?>
                                    <a class="nav-link mb-1<?= $active === $key ? ' active' : '' ?>" href="<?= $item[0] ?>" <?= $active === $key ? ' aria-current="page"' : '' ?>><?= $item[1] ?></a>
                                <?php endforeach ?>
                            </nav>
                        </details>
                    </aside>
                <?php endif ?>
                <div class="<?= $viewer ? 'col-12' : 'col-md-9 col-lg-10' ?> d-flex flex-column px-0">
                    <?php if ($viewer): ?>
                        <header class="flow-viewer-header border-bottom">
                            <div class="flow-viewer-brand">
                                <a class="flow-logo h3 d-flex align-items-center text-decoration-none mb-0" href="/viewer.php" aria-label="SIPATA Dasbor">
                                    <img class="flow-brand-mark" src="/assets/images/flow-mark-blue.svg" width="36" height="36" alt="" aria-hidden="true">
                                    <span>SIPATA</span>
                                </a>
                                <p class="small text-muted mb-0">Sistem Pemantauan Tinggi Air</p>
                            </div>
                            <nav class="flow-viewer-nav" aria-label="Navigasi pengunjung">
                                <?php foreach ($navigation as $key => $item): ?>
                                    <a class="btn <?= $active === $key ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= $item[0] ?>" <?= $active === $key ? 'aria-current="page"' : '' ?>><?= $item[1] ?></a>
                                <?php endforeach ?>
                                <a class="btn btn-outline-secondary" href="/login.php">Masuk</a>
                            </nav>
                        </header>
                    <?php else: ?>
                        <header class="flow-topbar bg-white border-bottom px-3 px-lg-4 py-3 d-flex flex-wrap justify-content-between align-items-center">
                            <span class="text-muted">Sistem pemantauan / <strong class="text-dark"><?= h($title) ?></strong></span>
                            <div class="d-flex align-items-center">
                                <span class="small d-flex align-items-center mr-3">
                                    <svg class="mr-2" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                        <path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm0 1c-2.67 0-5 1.34-5 3v2h10v-2c0-1.66-2.33-3-5-3Z" />
                                    </svg>
                                    <?= h(currentUser()['email']) ?>
                                </span>
                                <form method="post" action="/logout.php"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><button class="btn btn-outline-secondary" type="submit">Keluar</button></form>
                            </div>
                        </header>
                    <?php endif ?>
                    <main class="flow-main p-3 p-lg-4 flex-grow-1">
                        <?php if ($flash): ?><div class="alert alert-success" role="status"><?= h($flash) ?></div><?php endif ?>
                    <?php
                }

                function pageEnd(): void
                {
                    ?>
                    </main>
                    <footer class="flow-footer border-top px-3 px-lg-4 py-3 d-flex flex-wrap justify-content-between small text-muted">
                        <span class="flow-footer-brand">
                            <img class="flow-brand-mark" src="/assets/images/flow-mark-blue.svg" width="24" height="24" alt="" aria-hidden="true">
                            <span><strong>SIPATA</strong> · Sistem Pemantauan Tinggi Air</span>
                        </span>
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
        <p class="flow-eyebrow small text-primary font-weight-bold mb-1"><?= h($eyebrow) ?></p>
        <h1 class="flow-title h3"><?= h($title) ?></h1>
        <p class="text-muted mb-0"><?= h($description) ?></p>
    </div>
<?php
                }

                function deviceTable(array $devices): void
                {
?>
    <div class="table-responsive" tabindex="0" role="region" aria-label="Pembacaan perangkat">
        <table class="table table-striped table-hover mb-0">
            <thead class="thead-light text-nowrap">
                <tr>
                    <th scope="col">Pos Pantau</th>
                    <th scope="col">Status</th>
                    <th scope="col">Tinggi air</th>
                    <th scope="col">Status siaga</th>
                    <th scope="col">Buzzer</th>
                    <th scope="col">Interval</th>
                    <th scope="col">Firmware</th>
                    <th scope="col">Terakhir terhubung</th>
                    <th scope="col"><span class="sr-only">Tindakan</span></th>
                </tr>
            </thead>
            <tbody id="device-rows">
                <?php foreach ($devices as $device): ?>
                    <tr>
                        <td class="text-nowrap"><strong><?= h($device['station_name'] ?? 'Pos pantau belum dinamai') ?></strong><small class="d-block text-muted">ID: <?= h($device['device_id']) ?></small></td>
                        <td><span class="badge badge-<?= $device['device_status'] ? 'success' : 'danger' ?>"><?= $device['device_status'] ? 'Terhubung' : 'Terputus' ?></span></td>
                        <td class="text-nowrap"><strong><?= $device['water_level'] === null ? '—' : h(number_format((float)$device['water_level'], 2)) ?></strong> <span class="text-muted">cm</span></td>
                        <td><span class="badge badge-<?= [0 => 'success', 1 => 'warning', 2 => 'danger'][$device['alert_status'] ?? -1] ?? 'secondary' ?>"><?= h($device['water_level'] === null ? 'Belum ada pembacaan' : uiText(alertLabel($device['alert_status']))) ?></span></td>
                        <td class="text-nowrap"><?= $device['buzzer_status'] ? 'Aktif' : 'Nonaktif' ?><small class="d-block text-muted">Ambang batas &gt; <?= h($device['alarm_threshold']) ?> cm</small></td>
                        <td class="text-nowrap"><?= h($device['update_interval']) ?> detik</td>
                        <td class="text-nowrap"><?= h($device['reported_firmware'] ?? '—') ?><small class="d-block text-muted">Target <?= h($device['current_firmware'] ?? '—') ?></small></td>
                        <td class="text-nowrap"><?= h(uiText($device['last_seen_display'])) ?></td>
                        <td><a class="btn btn-sm btn-outline-primary" href="/device-edit.php?id=<?= h($device['id']) ?>">Ubah<span class="sr-only"> <?= h($device['device_id']) ?></span></a></td>
                    </tr>
                <?php endforeach ?>
                <?php if (!$devices): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-5">Belum ada perangkat. Tambahkan perangkat pertama untuk mulai memantau.</td>
                    </tr>
                <?php endif ?>
            </tbody>
        </table>
    </div>
    <?php
                }

                function waterLevelPanels(array $devices, bool $detailView = false, bool $viewer = false): void
                {
                    if (!$devices) {
    ?>
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center py-5">
                    <h2 class="h5">Belum ada perangkat yang dikonfigurasi</h2>
                    <?php if (!$viewer): ?><p class="text-muted">Tambahkan perangkat untuk mulai memantau tinggi air.</p>
                        <a class="btn btn-primary" href="/device-edit.php">Tambah perangkat</a><?php endif ?>
                </div>
            </div>
        </div>
    <?php
                        return;
                    }
                    foreach ($devices as $device) {
                        $level = $device['water_level'] === null ? null : (float) $device['water_level'];
                        $ranges = $device['alert_ranges'] ?? [];
                        foreach ($ranges as &$range) {
                            if ((int) $range['alarm_status'] === 2) {
                                $range['max_level'] = null;
                            }
                        }
                        unset($range);
                        $colors = [0 => 'success', 1 => 'warning', 2 => 'danger'];
                        $statusColor = $colors[$device['alert_status'] ?? -1] ?? 'secondary';
                        $bounds = [0.0, 1.0, $level ?? 0.0];
                        foreach ($ranges as $range) {
                            foreach (['min_level', 'max_level'] as $bound) {
                                if ($range[$bound] !== null) {
                                    $bounds[] = (float) $range[$bound];
                                }
                            }
                        }
                        // This is a display scale derived from readings/ranges, not physical capacity.
                        $minimum = min($bounds);
                        $maximum = max($bounds);
                        $rawStep = ($maximum - $minimum) / 5;
                        $magnitude = 10 ** floor(log10($rawStep));
                        $factor = $rawStep / $magnitude;
                        $step = ($factor <= 1 ? 1 : ($factor <= 2 ? 2 : ($factor <= 5 ? 5 : 10))) * $magnitude;
                        $minimum = floor($minimum / $step) * $step;
                        $maximum = ceil($maximum / $step) * $step + $step;
                        $span = $maximum - $minimum;
                        $waterY = $level === null ? null : 285 - (($level - $minimum) / $span * 270);
                        $markers = [];
                        foreach ($ranges as $range) {
                            $lower = max($minimum, $range['min_level'] === null ? $minimum : (float) $range['min_level']);
                            $upper = min($maximum, $range['max_level'] === null ? $maximum : (float) $range['max_level']);
                            if ($lower < $upper) {
                                $top = 285 - (($upper - $minimum) / $span * 270);
                                $bottom = 285 - (($lower - $minimum) / $span * 270);
                                $markers[] = ['status' => (int) $range['alarm_status'], 'top' => $top, 'bottom' => $bottom, 'label_y' => ($top + $bottom) / 2];
                            }
                        }
                        usort($markers, fn($a, $b) => $a['label_y'] <=> $b['label_y']);
                        $previousY = -6;
                        foreach ($markers as &$marker) {
                            $marker['label_y'] = max(24, $marker['label_y'], $previousY + 30);
                            $previousY = $marker['label_y'];
                        }
                        unset($marker);
                        if ($previousY > 276) {
                            foreach ($markers as &$marker) {
                                $marker['label_y'] -= $previousY - 276;
                            }
                            unset($marker);
                        }
    ?>
        <div class="<?= count($devices) === 1 ? 'col-12' : 'col-12 col-lg-6' ?> mb-4">
            <article class="flow-device-card card h-100" data-alert-device="<?= (int) $device['id'] ?>" data-alert-status="<?= $device['alert_status'] === null ? '' : (int) $device['alert_status'] ?>">
                <div class="card-header bg-white d-flex justify-content-between align-items-start">
                    <div class="mr-3 text-break">
                        <h2 class="h4 mb-1"><?= h($device['station_name'] ?? 'Pos pantau belum dinamai') ?></h2>
                        <p class="flow-device-id text-muted mb-0"><?= h($device['device_id']) ?></p>
                    </div>
                    <span class="badge badge-<?= ($viewer || $device['device_status']) ? 'success' : 'danger' ?>"><?= ($viewer || $device['device_status']) ? 'Terhubung' : 'Terputus' ?></span>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-sm-7 mb-2 mb-sm-0">
                            <span class="badge badge-<?= $statusColor ?>"><?= h($level === null ? 'Belum ada pembacaan' : uiText(alertLabel($device['alert_status']))) ?></span>
                            <p class="flow-level mb-0 mt-2 text-break"><strong class="h2"><?= $level === null ? '—' : h(number_format($level, 1)) ?></strong> <span class="text-muted">cm</span></p>
                            <small class="d-block text-muted">Tinggi air dari titik acuan nol</small>
                            <small class="text-muted">Skala tampilan: <?= h(number_format($minimum, 1)) ?>–<?= h(number_format($maximum, 1)) ?> cm</small>
                        </div>
                        <?php if (!$viewer): ?><div class="col-sm-5 text-sm-right">
                                <small class="text-muted d-block">Pengukuran terakhir</small>
                                <span><?= h(uiText($device['last_seen_display'])) ?></span>
                                <?php if (!$viewer && !$device['device_status'] && $level !== null): ?><small class="d-block text-muted">Pembacaan terakhir · perangkat terputus</small><?php endif ?>
                            </div><?php endif ?>
                    </div>
                    <?php foreach (count($devices) === 1 && !$detailView ? [1000 => 'd-none d-md-block', 420 => 'd-md-none'] : [420 => 'd-block'] as $width => $visibility): ?>
                        <?php $graphicId = 'water-level-' . (int) $device['id'] . '-' . $width; ?>
                        <svg class="flow-water-chart w-100 <?= $visibility ?> border rounded" viewBox="0 0 <?= $width ?> 300" role="img" aria-labelledby="<?= $graphicId ?>-title <?= $graphicId ?>-description">
                            <title id="<?= $graphicId ?>-title">Tinggi air · <?= h($device['station_name'] ?? 'Pos pantau belum dinamai') ?></title>
                            <desc id="<?= $graphicId ?>-description"><?= $level === null ? 'Belum ada pembacaan sensor.' : h(number_format($level, 1)) . ' cm.' ?> Rentang dan batas siaga mengikuti pengaturan perangkat. Skala menyesuaikan secara otomatis.</desc>
                            <defs>
                                <linearGradient id="<?= $graphicId ?>-fill" x1="0" y1="0" x2="0" y2="1">
                                    <stop class="text-info" offset="0" stop-color="currentColor" />
                                    <stop class="text-primary" offset="1" stop-color="currentColor" />
                                </linearGradient>
                                <clipPath id="<?= $graphicId ?>-surface-clip">
                                    <rect x="58" y="15" width="<?= $width - 68 ?>" height="270" />
                                </clipPath>
                            </defs>
                            <rect class="text-light" width="<?= $width ?>" height="300" fill="currentColor" />
                            <?php foreach ($markers as $marker): ?>
                                <rect class="text-<?= $colors[$marker['status']] ?>" x="58" y="<?= h($marker['top']) ?>" width="<?= $width - 68 ?>" height="<?= h($marker['bottom'] - $marker['top']) ?>" fill="currentColor" fill-opacity="0.08" />
                            <?php endforeach ?>
                            <?php if ($waterY !== null): ?>
                                <rect x="58" y="<?= h($waterY) ?>" width="<?= $width - 68 ?>" height="<?= h(285 - $waterY) ?>" fill="url(#<?= $graphicId ?>-fill)" />
                                <line class="flow-water-surface-static text-info" x1="58" x2="<?= $width - 10 ?>" y1="<?= h($waterY) ?>" y2="<?= h($waterY) ?>" stroke="currentColor" stroke-width="3" />
                                <g clip-path="url(#<?= $graphicId ?>-surface-clip)" aria-hidden="true">
                                    <svg x="58" y="<?= h($waterY - 8) ?>" width="<?= $width - 68 ?>" height="16" overflow="hidden">
                                        <path class="flow-water-wave" d="M-160 8 Q-120 0 -80 8 T0 8 Q40 0 80 8 T160 8 Q200 0 240 8 T320 8 Q360 0 400 8 T480 8 Q520 0 560 8 T640 8 Q680 0 720 8 T800 8 Q840 0 880 8 T960 8 Q1000 0 1040 8 T1120 8" />
                                    </svg>
                                </g>
                            <?php endif ?>
                            <?php for ($tick = $minimum, $i = 0; $tick <= $maximum + $step / 10 && $i < 12; $tick += $step, $i++): ?>
                                <?php $y = 285 - (($tick - $minimum) / $span * 270); ?>
                                <line class="text-secondary" x1="48" x2="<?= $width - 10 ?>" y1="<?= h($y) ?>" y2="<?= h($y) ?>" stroke="currentColor" stroke-opacity="0.15" />
                                <text class="text-secondary" x="42" y="<?= h($y + 5) ?>" fill="currentColor" text-anchor="end" font-size="14"><?= h(number_format($tick, $step < 1 ? 2 : 0)) ?></text>
                            <?php endfor ?>
                            <?php foreach ($markers as $marker): ?>
                                <g class="text-<?= $colors[$marker['status']] ?>">
                                    <?php if ($marker['bottom'] > 15.01 && $marker['bottom'] < 284.99): ?>
                                        <line x1="58" x2="<?= $width - 10 ?>" y1="<?= h($marker['bottom']) ?>" y2="<?= h($marker['bottom']) ?>" stroke="currentColor" stroke-dasharray="6 4" stroke-width="1.5" /><?php endif ?>
                                    <rect x="<?= $width - 88 ?>" y="<?= h($marker['label_y'] - 9) ?>" width="68" height="18" rx="3" fill="white" stroke="currentColor" />
                                    <text class="font-weight-bold" x="<?= $width - 54 ?>" y="<?= h($marker['label_y'] + 4) ?>" fill="currentColor" text-anchor="middle" font-size="11"><?= h(uiText(alertLabel($marker['status']))) ?></text>
                                </g>
                            <?php endforeach ?>
                            <?php if ($level === null): ?><text class="text-secondary" x="<?= ($width + 48) / 2 ?>" y="155" fill="currentColor" text-anchor="middle" font-size="16">Menunggu data sensor</text><?php endif ?>
                        </svg>
                    <?php endforeach ?>
                    <div class="flow-thresholds row mt-3">
                        <?php foreach ($ranges as $range): ?>
                            <div class="col-md-4 mb-2">
                                <span class="badge badge-<?= $colors[(int) $range['alarm_status']] ?>"><?= h(uiText(alertLabel($range['alarm_status']))) ?></span>
                                <small class="text-muted text-break">
                                    <?php if ($range['min_level'] !== null): ?>≥ <?= h(number_format((float) $range['min_level'], 1)) ?> cm<?php endif ?>
                                    <?php if ($range['min_level'] !== null && $range['max_level'] !== null): ?> dan <?php endif ?>
                                    <?php if ($range['max_level'] !== null): ?>&lt; <?= h(number_format((float) $range['max_level'], 1)) ?> cm<?php endif ?>
                                </small>
                            </div>
                        <?php endforeach ?>
                    </div>
                    <div class="flow-buzzer border-top pt-3 mt-2 d-flex flex-wrap justify-content-between small">
                        <span class="mr-3 mb-1">Buzzer: <span class="badge badge-<?= $device['buzzer_status'] ? 'primary' : 'secondary' ?>"><?= $device['buzzer_status'] ? 'Aktif' : 'Nonaktif' ?></span></span>
                        <span class="text-muted">Firmware terlapor: <?= h($device['reported_firmware'] ?? 'Belum dilaporkan') ?></span>
                    </div>
                </div>
                <?php if (!$detailView && !$viewer): ?>
                    <div class="card-footer bg-white text-right">
                        <a class="btn btn-outline-primary" href="/device-history.php?<?= h(http_build_query(['device_id' => $device['device_id']])) ?>">Lihat detail dan riwayat</a>
                    </div>
                <?php endif ?>
            </article>
        </div>
<?php
                    }
                }
