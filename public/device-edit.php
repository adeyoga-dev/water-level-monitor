<?php
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/admin.php';
require dirname(__DIR__) . '/app/layout.php';
startSession();
$id = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
if ($id === false) {
    http_response_code(404);
    exit('Device not found.');
}
$device = $id ? query('SELECT * FROM devices WHERE id=?', [$id])->fetch() : ['device_id' => '', 'alarm_threshold' => '', 'buzzer_status' => 0, 'update_interval' => 60];
if (!$device) {
    http_response_code(404);
    exit('Device not found.');
}
$ranges = $id ? query('SELECT * FROM device_alert_ranges WHERE device_id=? ORDER BY alarm_status', [$id])->fetchAll() : array_fill(0, 3, ['min_level' => '', 'max_level' => '']);
$values = $device + ['hours' => intdiv((int)$device['update_interval'], 3600), 'minutes' => intdiv((int)$device['update_interval'] % 3600, 60), 'seconds' => (int)$device['update_interval'] % 60];
$values['coordinates'] = ($device['latitude'] ?? null) !== null && ($device['longitude'] ?? null) !== null ? $device['latitude'] . ', ' . $device['longitude'] : '';
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        checkCsrf();
        saveDevice(array_replace($_POST, ['id' => $id]));
        $_SESSION['flash'] = 'Device settings saved successfully.';
        header('Location: /devices.php');
        exit;
    } catch (InvalidArgumentException $e) {
        http_response_code(422);
        $error = $e->getMessage();
        $values = $_POST;
        $ranges = $_POST['ranges'] ?? $ranges;
    }
}
pageStart($id ? 'Edit device' : 'Add device', 'devices');
pageTitle('DEVICE SETTINGS', $id ? 'Edit device' : 'Add device', 'Set the device identity and its response to water levels.');
?>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= h($error) ?></div><?php endif ?>
<form method="post">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
    <input type="hidden" name="id" value="<?= h($id) ?>">
    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h2 class="h5 mb-0">Device configuration</h2>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label for="device-id">Device ID</label>
                        <input class="form-control" id="device-id" name="device_id" maxlength="64" pattern="[A-Za-z0-9_-]+" required value="<?= h($values['device_id'] ?? '') ?>" <?= $id ? 'readonly' : '' ?> placeholder="ESP32-001" aria-describedby="device-id-help">
                        <small class="form-text text-muted" id="device-id-help">Must match the ID in the ESP32 firmware exactly. Cannot be changed after creation.</small>
                    </div>
                    <div class="form-group">
                        <label for="device-coordinates">Location coordinates</label>
                        <input class="form-control" id="device-coordinates" name="coordinates" maxlength="100" placeholder="M7CM+GJ5 Sukaluyu, Karawang, West Java" value="<?= h(is_string($values['coordinates'] ?? '') ? ($values['coordinates'] ?? '') : '') ?>" aria-describedby="coordinates-help" autocomplete="off" spellcheck="false">
                        <small class="form-text text-muted" id="coordinates-help">Paste plus code from Google Maps. Use decimal points and a comma between the numbers, not a share link. Leave blank to remove the location. Only the current location is saved.</small>
                        <a class="small d-inline-block mt-2" href="/maps.php">View device locations →</a>
                    </div>
                    <fieldset class="form-group" aria-describedby="interval-help">
                        <legend class="h6">Reporting interval</legend>
                        <div class="form-row">
                            <?php foreach (['hours' => 'Hours', 'minutes' => 'Minutes', 'seconds' => 'Seconds'] as $key => $label): ?>
                                <div class="col-4">
                                    <label class="small" for="interval-<?= $key ?>"><?= $label ?></label>
                                    <input class="form-control" id="interval-<?= $key ?>" type="number" name="<?= $key ?>" min="0" max="<?= $key === 'hours' ? 23 : 59 ?>" required value="<?= h($values[$key] ?? 0) ?>">
                                </div>
                            <?php endforeach ?>
                        </div>
                        <small class="form-text text-muted" id="interval-help">The sensor is sampled every 5 seconds. Readings are sent at this interval, with a minimum of 1 second.</small>
                    </fieldset>
                    <div class="form-group">
                        <label for="alarm-threshold">Alarm threshold (cm)</label>
                        <input class="form-control" id="alarm-threshold" type="number" step="any" name="alarm_threshold" required value="<?= h($values['alarm_threshold'] ?? '') ?>" placeholder="Example: 20">
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" id="buzzer-status" type="checkbox" name="buzzer_status" value="1" <?= !empty($values['buzzer_status']) ? 'checked' : '' ?> aria-describedby="buzzer-help">
                        <label class="form-check-label" for="buzzer-status">Enable buzzer</label>
                    </div>
                    <small class="form-text text-muted" id="buzzer-help">The buzzer sounds when enabled and the water level exceeds the threshold. It stays off at the exact threshold.</small>
                </div>
            </div>
        </div>
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h2 class="h5 mb-0">Alert label ranges</h2>
                </div>
                <div class="card-body">
                    <p class="small text-muted">The lower bound is inclusive; the upper bound is exclusive. Siaga 3 uses only a required lower bound, with no upper limit. Ranges must not overlap. Siaga 1 upper bound must differ from Siaga 2 lower bound; Siaga 2 upper bound must differ from Siaga 3 lower bound.</p>
                    <?php for ($i = 0; $i < 3; $i++): ?>
                        <fieldset class="border-bottom mb-3 pb-3">
                            <legend class="h6"><span class="badge badge-<?= ['success', 'warning', 'danger'][$i] ?>">Siaga <?= $i + 1 ?></span></legend>
                            <div class="form-row">
                                <div class="<?= $i === 2 ? 'col-12' : 'col-sm-6 mb-2 mb-sm-0' ?>">
                                    <label for="range-<?= $i ?>-min">Lower bound (≥ cm)</label>
                                    <input class="form-control" id="range-<?= $i ?>-min" type="number" step="any" name="ranges[<?= $i ?>][min_level]" value="<?= h($ranges[$i]['min_level'] ?? '') ?>" placeholder="<?= $i === 2 ? 'Required' : 'Unbounded' ?>" <?= $i === 2 ? 'required' : '' ?>>
                                </div>
                                <?php if ($i !== 2): ?>
                                    <div class="col-sm-6">
                                        <label for="range-<?= $i ?>-max">Upper bound (&lt; cm)</label>
                                        <input class="form-control" id="range-<?= $i ?>-max" type="number" step="any" name="ranges[<?= $i ?>][max_level]" value="<?= h($ranges[$i]['max_level'] ?? '') ?>" placeholder="Unbounded">
                                    </div>
                                <?php endif ?>
                            </div>
                        </fieldset>
                    <?php endfor ?>
                    <p class="small text-muted mb-0">Values outside all ranges are recorded as Unclassified. Example ranges: empty–19, 20–39, 40 and above.</p>
                </div>
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-end">
        <a class="btn btn-secondary mr-2" href="/devices.php">Back</a>
        <button class="btn btn-primary" type="submit">Save device</button>
    </div>
</form>
<script src="/assets/device-edit.js" defer></script>
<script src="/assets/plus-code-to-coordinate.js" defer></script>
<?php pageEnd(); ?>