<?php
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/admin.php';
require dirname(__DIR__) . '/app/layout.php';
startSession();
$id = filter_var($_GET['id'] ?? 0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
if ($id === false) { http_response_code(404); exit('Device not found.'); }
$device = $id ? query('SELECT * FROM devices WHERE id=?',[$id])->fetch() : ['device_id'=>'','alarm_threshold'=>'','buzzer_status'=>0,'update_interval'=>60];
if (!$device) { http_response_code(404); exit('Device not found.'); }
$ranges = $id ? query('SELECT * FROM device_alert_ranges WHERE device_id=? ORDER BY alarm_status',[$id])->fetchAll() : array_fill(0,3,['min_level'=>'','max_level'=>'']);
$values = $device + ['hours'=>intdiv((int)$device['update_interval'],3600),'minutes'=>intdiv((int)$device['update_interval']%3600,60),'seconds'=>(int)$device['update_interval']%60];
$error = null;
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        checkCsrf();
        saveDevice($_POST + ['id'=>$id]);
        $_SESSION['flash']='Device settings saved successfully.';
        header('Location: /devices.php'); exit;
    } catch (InvalidArgumentException $e) { $error=$e->getMessage(); $values=$_POST; $ranges=$_POST['ranges']??$ranges; }
}
pageStart($id?'Edit device':'Add device','devices');
pageTitle('DEVICE SETTINGS',$id?'Edit device':'Add device','Set the device identity and its response to water levels.');
?>
<?php if ($error): ?><div class="notice error"><?= h($error) ?></div><?php endif ?>
<form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="id" value="<?= h($id) ?>"><div class="form-grid"><div class="panel form-panel"><h2>Device configuration</h2><label>Device ID<input name="device_id" maxlength="64" pattern="[A-Za-z0-9_-]+" required value="<?= h($values['device_id']??'') ?>" <?= $id?'readonly':'' ?> placeholder="ESP32-001"></label><p class="hint">Must match the ID in the ESP32 firmware exactly. Cannot be changed after creation.</p><label>Reporting interval</label><div class="duration"><?php foreach (['hours'=>'Hours','minutes'=>'Minutes','seconds'=>'Seconds'] as $key=>$label): ?><label><input type="number" name="<?= $key ?>" min="0" max="<?= $key==='hours'?23:59 ?>" required value="<?= h($values[$key]??0) ?>"><small><?= $label ?></small></label><?php endforeach ?></div><p class="hint">The sensor is sampled every 5 seconds. Readings are sent at this interval, with a minimum of 1 second.</p><label>Alarm threshold (cm)<input type="number" step="any" name="alarm_threshold" required value="<?= h($values['alarm_threshold']??'') ?>" placeholder="Example: 20"></label><label class="switch-label"><input type="checkbox" name="buzzer_status" value="1" <?= !empty($values['buzzer_status'])?'checked':'' ?>><span>Enable buzzer</span></label><p class="hint">The buzzer sounds when enabled and the water level exceeds the threshold. It stays off at the exact threshold.</p></div><div class="panel form-panel"><h2>Alert label ranges</h2><p class="hint">The lower bound is inclusive; the upper bound is exclusive. Leave a bound empty for an open end. Ranges must not overlap.</p><?php for ($i=0;$i<3;$i++): ?><div class="range-block"><h3><span class="alert-label alert-<?= $i ?>">Siaga <?= $i+1 ?></span></h3><div class="two-columns"><label>Lower bound (≥ cm)<input type="number" step="any" name="ranges[<?= $i ?>][min_level]" value="<?= h($ranges[$i]['min_level']??'') ?>" placeholder="Unbounded"></label><label>Upper bound (&lt; cm)<input type="number" step="any" name="ranges[<?= $i ?>][max_level]" value="<?= h($ranges[$i]['max_level']??'') ?>" placeholder="Unbounded"></label></div></div><?php endfor ?><p class="hint">Values outside all ranges are recorded as Unclassified. Example consecutive ranges: empty–20, 20–40, 40–empty.</p></div></div><div class="form-actions"><a class="button secondary" href="/devices.php">Back</a><button class="button" type="submit">Save device</button></div></form>
<?php pageEnd(); ?>
