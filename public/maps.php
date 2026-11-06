<?php
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/admin.php';
require dirname(__DIR__) . '/app/layout.php';
$devices = deviceList();
$mapDevices = [];
foreach ($devices as $device) {
    if (($device['latitude'] ?? null) === null || ($device['longitude'] ?? null) === null) {
        continue;
    }
    $mapDevices[] = [
        'id' => (int) $device['id'],
        'device_id' => $device['device_id'],
        'latitude' => (float) $device['latitude'],
        'longitude' => (float) $device['longitude'],
        'online' => (bool) $device['device_status'],
        'level' => $device['water_level'] === null ? null : (float) $device['water_level'],
        'alert' => $device['water_level'] === null ? 'No readings yet' : alertLabel($device['alert_status']),
        'last_seen' => $device['last_seen_display'],
    ];
}
pageStart('Maps', 'maps');
pageTitle('DEVICE LOCATIONS', 'Water monitoring across Indonesia.', 'View current device locations. Set or replace coordinates in Devices; location history is not recorded.');
?>
<section class="card mb-4" aria-labelledby="maps-title">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h2 class="h5 mb-1" id="maps-title">Device locations</h2>
            <span class="small text-muted"><?= count($mapDevices) ?> mapped · <?= count($devices) - count($mapDevices) ?> without coordinates</span>
        </div>
        <div class="d-flex flex-wrap flow-map-actions">
            <button class="btn btn-outline-secondary" id="map-indonesia" type="button" disabled>Show Indonesia</button>
            <a class="btn btn-outline-secondary" href="/maps.php">Reload data</a>
        </div>
    </div>
    <div class="card-body">
        <p class="small text-muted" id="map-status" role="status" aria-live="polite">Loading map…</p>
        <div class="alert alert-danger" id="map-error" role="alert" hidden></div>
        <?php if (!$mapDevices): ?><p class="text-muted">No device locations yet. Add coordinates in <a href="/devices.php">Devices</a> to place your first pin.</p><?php endif ?>
        <div id="devices-map" class="flow-map" role="region" aria-label="Map of device locations" aria-busy="true"></div>
        <p class="small text-muted mt-3 mb-0">Select a pin or a device below. Status is a snapshot from this page load; use Reload data for updates.</p>
        <noscript>
            <p class="text-muted">Enable JavaScript to view the interactive map. Device locations remain available in the table below.</p>
        </noscript>
    </div>
</section>
<section class="card" aria-labelledby="map-device-list-title">
    <div class="card-header">
        <h2 class="h5 mb-0" id="map-device-list-title">Devices</h2>
    </div>
    <div class="table-responsive" tabindex="0" role="region" aria-label="Device locations list">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th scope="col">Device</th>
                    <th scope="col">Status</th>
                    <th scope="col">Coordinates</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($devices as $device): ?>
                    <tr>
                        <td><strong><?= h($device['device_id']) ?></strong></td>
                        <td><span class="badge badge-<?= $device['device_status'] ? 'success' : 'secondary' ?>"><?= $device['device_status'] ? 'Online' : 'Offline' ?></span></td>
                        <td class="text-nowrap"><?php if (($device['latitude'] ?? null) !== null && ($device['longitude'] ?? null) !== null): ?><?= h($device['latitude']) ?>, <?= h($device['longitude']) ?><?php else: ?><span class="text-muted">Not set</span><?php endif ?></td>
                        <td>
                            <div class="d-flex flex-wrap flow-map-actions">
                                <?php if (($device['latitude'] ?? null) !== null && ($device['longitude'] ?? null) !== null): ?><button class="btn btn-outline-secondary" type="button" data-map-device="<?= (int) $device['id'] ?>" disabled>Show on map<span class="sr-only"> <?= h($device['device_id']) ?></span></button><?php endif ?>
                                <a class="btn btn-outline-secondary" href="/device-edit.php?id=<?= (int) $device['id'] ?>">Edit location<span class="sr-only"> <?= h($device['device_id']) ?></span></a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach ?>
                <?php if (!$devices): ?><tr>
                        <td colspan="4" class="text-center text-muted py-5">No devices yet. <a href="/device-edit.php">Add a device</a> to get started.</td>
                    </tr><?php endif ?>
            </tbody>
        </table>
    </div>
</section>
<script id="map-data" type="application/json">
    <?= json_encode($mapDevices, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
</script>
<script src="/assets/vendor/leaflet-1.9.4/leaflet.js" defer></script>
<script src="/assets/maps.js" defer></script>
<?php pageEnd(); ?>