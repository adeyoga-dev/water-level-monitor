<?php
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/admin.php';
require dirname(__DIR__) . '/app/layout.php';
$viewer = defined('FLOW_VIEWER') && FLOW_VIEWER;
$devices = deviceList(!$viewer);
$mapDevices = [];
foreach ($devices as $device) {
    if (($device['latitude'] ?? null) === null || ($device['longitude'] ?? null) === null) {
        continue;
    }
    $mapDevices[] = [
        'id' => (int) $device['id'],
        'device_id' => $device['device_id'],
        'station_name' => $device['station_name'] ?? 'Pos pantau belum dinamai',
        'latitude' => (float) $device['latitude'],
        'longitude' => (float) $device['longitude'],
        'online' => $viewer || (bool) $device['device_status'],
        'level' => $device['water_level'] === null ? null : (float) $device['water_level'],
        'alert' => $device['water_level'] === null ? 'Belum ada pembacaan' : uiText(alertLabel($device['alert_status'])),
        'last_seen' => uiText($device['last_seen_display']),
    ];
}
pageStart('Peta', 'maps', $viewer);
// pageTitle('LOKASI PERANGKAT', 'Water monitoring across Indonesia.', $viewer ? 'Lihat lokasi perangkat dan tinggi air saat ini.' : 'Lihat lokasi perangkat saat ini. Atur atau ganti koordinat di Perangkat; riwayat lokasi tidak dicatat.');
?>
<section class="card mb-4" aria-labelledby="maps-title">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h2 class="h5 mb-1" id="maps-title">Sebaran Pos Pantau</h2>
            <span class="small text-muted"><?= count($mapDevices) ?> terpetakan · <?= count($devices) - count($mapDevices) ?> tanpa koordinat</span>
        </div>
        <div class="d-flex flex-wrap flow-map-actions">
            <button class="btn btn-outline-secondary" id="map-indonesia" type="button" disabled>Perkecil</button>
            <a class="btn btn-outline-secondary" href="<?= $viewer ? '/viewer-maps.php' : '/maps.php' ?>">Muat ulang data</a>
        </div>
    </div>
    <div class="card-body">
        <p class="small text-muted" id="map-status" role="status" aria-live="polite">Memuat peta…</p>
        <div class="alert alert-danger" id="map-error" role="alert" hidden></div>
        <?php if (!$mapDevices): ?><p class="text-muted">Belum ada lokasi perangkat.<?php if (!$viewer): ?> Tambahkan koordinat di <a href="/devices.php">Perangkat</a> untuk menempatkan penanda pertama.<?php endif ?></p><?php endif ?>
        <div id="devices-map" data-viewer="<?= $viewer ? 'true' : 'false' ?>" class="flow-map" role="region" aria-label="Peta lokasi perangkat" aria-busy="true"></div>
        <noscript>
            <p class="text-muted">Aktifkan JavaScript untuk melihat peta interaktif dan informasi pos pantau.</p>
        </noscript>
    </div>
</section>
<script id="map-data" type="application/json">
    <?= json_encode($mapDevices, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
</script>
<script src="/assets/vendor/leaflet-1.9.4/leaflet.js" defer></script>
<script src="/assets/maps.js" defer></script>
<?php pageEnd(); ?>
