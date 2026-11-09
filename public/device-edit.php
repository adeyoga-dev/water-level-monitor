<?php
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/admin.php';
require dirname(__DIR__) . '/app/layout.php';
startSession();
$id = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
if ($id === false) {
    http_response_code(404);
    exit('Perangkat tidak ditemukan.');
}
$device = $id ? query('SELECT * FROM devices WHERE id=?', [$id])->fetch() : ['device_id' => '', 'alarm_threshold' => '', 'buzzer_status' => 0, 'update_interval' => 60];
if (!$device) {
    http_response_code(404);
    exit('Perangkat tidak ditemukan.');
}
$ranges = $id ? query('SELECT * FROM device_alert_ranges WHERE device_id=? ORDER BY alarm_status', [$id])->fetchAll() : array_fill(0, 3, ['min_level' => '', 'max_level' => '']);
$values = $device + ['hours' => intdiv((int)$device['update_interval'], 3600), 'minutes' => intdiv((int)$device['update_interval'] % 3600, 60), 'seconds' => (int)$device['update_interval'] % 60];
$values['coordinates'] = ($device['latitude'] ?? null) !== null && ($device['longitude'] ?? null) !== null ? $device['latitude'] . ', ' . $device['longitude'] : '';
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        checkCsrf();
        saveDevice(array_replace($_POST, ['id' => $id]));
        $_SESSION['flash'] = 'Pengaturan perangkat berhasil disimpan.';
        header('Location: /devices.php');
        exit;
    } catch (InvalidArgumentException $e) {
        http_response_code(422);
        $error = $e->getMessage();
        $values = $_POST;
        $ranges = $_POST['ranges'] ?? $ranges;
    }
}
pageStart($id ? 'Ubah perangkat' : 'Tambah perangkat', 'devices');
pageTitle('PENGATURAN PERANGKAT', $id ? 'Ubah perangkat' : 'Tambah perangkat', 'Atur identitas perangkat dan responsnya terhadap tinggi air.');
?>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= h(uiText($error)) ?></div><?php endif ?>
<form method="post">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
    <input type="hidden" name="id" value="<?= h($id) ?>">
    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h2 class="h5 mb-0">Konfigurasi perangkat</h2>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label for="device-id">ID perangkat</label>
                        <input class="form-control" id="device-id" name="device_id" maxlength="64" pattern="[A-Za-z0-9_-]+" required value="<?= h($values['device_id'] ?? '') ?>" <?= $id ? 'readonly' : '' ?> placeholder="ESP32-001" aria-describedby="device-id-help">
                        <small class="form-text text-muted" id="device-id-help">Harus sama persis dengan ID pada firmware ESP32. Tidak dapat diubah setelah dibuat.</small>
                    </div>
                    <div class="form-group">
                        <label for="device-coordinates">Koordinat lokasi</label>
                        <input class="form-control" id="device-coordinates" name="coordinates" maxlength="100" placeholder="M7CM+GJ5 Sukaluyu, Karawang, West Java" value="<?= h(is_string($values['coordinates'] ?? '') ? ($values['coordinates'] ?? '') : '') ?>" aria-describedby="coordinates-help" autocomplete="off" spellcheck="false">
                        <small class="form-text text-muted" id="coordinates-help">Tempel Plus Code dari Google Peta. Kosongkan untuk menghapus lokasi. Hanya lokasi saat ini yang disimpan.</small>
                        <a class="small d-inline-block mt-2" href="/maps.php">Lihat lokasi perangkat →</a>
                    </div>
                    <fieldset class="form-group" aria-describedby="interval-help">
                        <legend class="h6">Interval pelaporan</legend>
                        <div class="form-row">
                            <?php foreach (['hours' => 'Jam', 'minutes' => 'Menit', 'seconds' => 'Detik'] as $key => $label): ?>
                                <div class="col-4">
                                    <label class="small" for="interval-<?= $key ?>"><?= $label ?></label>
                                    <input class="form-control" id="interval-<?= $key ?>" type="number" name="<?= $key ?>" min="0" max="<?= $key === 'hours' ? 23 : 59 ?>" required value="<?= h($values[$key] ?? 0) ?>">
                                </div>
                            <?php endforeach ?>
                        </div>
                        <small class="form-text text-muted" id="interval-help">Sensor dibaca setiap 5 detik. Data dikirim sesuai interval ini, minimal 1 detik.</small>
                    </fieldset>
                    <div class="form-group">
                        <label for="alarm-threshold">Ambang alarm (cm)</label>
                        <input class="form-control" id="alarm-threshold" type="number" step="any" name="alarm_threshold" required value="<?= h($values['alarm_threshold'] ?? '') ?>" placeholder="Contoh: 20">
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" id="buzzer-status" type="checkbox" name="buzzer_status" value="1" <?= !empty($values['buzzer_status']) ? 'checked' : '' ?> aria-describedby="buzzer-help">
                        <label class="form-check-label" for="buzzer-status">Aktifkan buzzer</label>
                    </div>
                    <small class="form-text text-muted" id="buzzer-help">Buzzer berbunyi jika diaktifkan dan tinggi air melebihi ambang batas. Buzzer tetap mati tepat pada ambang batas.</small>
                </div>
            </div>
        </div>
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h2 class="h5 mb-0">Rentang status siaga</h2>
                </div>
                <div class="card-body">
                    <p class="small text-muted">Batas bawah termasuk rentang; batas atas tidak termasuk. Siaga 3 wajib memiliki batas bawah tanpa batas atas. Rentang tidak boleh tumpang tindih. Batas atas Siaga 1 harus berbeda dari batas bawah Siaga 2; batas atas Siaga 2 harus berbeda dari batas bawah Siaga 3.</p>
                    <?php for ($i = 0; $i < 3; $i++): ?>
                        <fieldset class="border-bottom mb-3 pb-3">
                            <legend class="h6"><span class="badge badge-<?= ['success', 'warning', 'danger'][$i] ?>">Siaga <?= $i + 1 ?></span></legend>
                            <div class="form-row">
                                <div class="<?= $i === 2 ? 'col-12' : 'col-sm-6 mb-2 mb-sm-0' ?>">
                                    <label for="range-<?= $i ?>-min">Batas bawah (≥ cm)</label>
                                    <input class="form-control" id="range-<?= $i ?>-min" type="number" step="any" name="ranges[<?= $i ?>][min_level]" value="<?= h($ranges[$i]['min_level'] ?? '') ?>" placeholder="<?= $i === 2 ? 'Wajib diisi' : 'Tanpa batas' ?>" <?= $i === 2 ? 'required' : '' ?>>
                                </div>
                                <?php if ($i !== 2): ?>
                                    <div class="col-sm-6">
                                        <label for="range-<?= $i ?>-max">Batas atas (&lt; cm)</label>
                                        <input class="form-control" id="range-<?= $i ?>-max" type="number" step="any" name="ranges[<?= $i ?>][max_level]" value="<?= h($ranges[$i]['max_level'] ?? '') ?>" placeholder="Tanpa batas">
                                    </div>
                                <?php endif ?>
                            </div>
                        </fieldset>
                    <?php endfor ?>
                    <p class="small text-muted mb-0">Nilai di luar semua rentang dicatat sebagai Tidak terklasifikasi. Contoh rentang: kosong–19, 20–39, 40 ke atas.</p>
                </div>
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-end">
        <a class="btn btn-secondary mr-2" href="/devices.php">Kembali</a>
        <button class="btn btn-primary" type="submit">Simpan perangkat</button>
    </div>
</form>
<script src="/assets/device-edit.js" defer></script>
<script src="/assets/plus-code-to-coordinate.js" defer></script>
<?php pageEnd(); ?>