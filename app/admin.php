<?php
declare(strict_types=1);

function saveDevice(array $input): int
{
    $id = filter_var($input['id'] ?? 0, FILTER_VALIDATE_INT, ['options'=>['min_range'=>0]]);
    if ($id === false) fail('Invalid ID.');
    $identifier = $input['device_id'] ?? '';
    if (!is_string($identifier) || !preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $identifier)) fail('Device ID must contain 1–64 letters, numbers, hyphens, or underscores.');
    $threshold = finiteNumber($input['alarm_threshold'] ?? null, 'Threshold');
    $parts = [];
    foreach (['hours'=>23,'minutes'=>59,'seconds'=>59] as $key=>$max) {
        $value = filter_var($input[$key] ?? '', FILTER_VALIDATE_INT, ['options'=>['min_range'=>0,'max_range'=>$max]]);
        if ($value === false) fail('Duration must use hours 0–23, minutes and seconds 0–59.');
        $parts[$key] = $value;
    }
    $interval = $parts['hours'] * 3600 + $parts['minutes'] * 60 + $parts['seconds'];
    if ($interval < 1) fail('The minimum interval is 1 second.');
    $ranges = validateRanges($input['ranges'] ?? []);
    $enabled = isset($input['buzzer_status']) ? 1 : 0;
    db()->beginTransaction();
    try {
        // Serialize with global firmware publication, including devices created during an upload.
        if ((int) query("SELECT GET_LOCK('water_level_firmware',10)")->fetchColumn() !== 1) throw new RuntimeException('Could not lock firmware updates.');
        $firmware = latestFirmware();
        $time = now();
        if ($id) {
            $existing = query('SELECT * FROM devices WHERE id=? FOR UPDATE', [$id])->fetch();
            if (!$existing) fail('Device not found.');
            if ($existing['device_id'] !== $identifier) fail('Device ID cannot be changed.');
            query('UPDATE devices SET update_interval=?,alarm_threshold=?,buzzer_status=?,updated_at=? WHERE id=?', [$interval,$threshold,$enabled,$time,$id]);
            query('DELETE FROM device_alert_ranges WHERE device_id=?', [$id]);
        } else {
            query('INSERT INTO devices(device_id,update_interval,alarm_threshold,buzzer_status,current_firmware,created_at,updated_at) VALUES(?,?,?,?,?,?,?)', [$identifier,$interval,$threshold,$enabled,$firmware ? $firmware['version'] : null,$time,$time]);
            $id = (int) db()->lastInsertId();
        }
        foreach ($ranges as $range) query('INSERT INTO device_alert_ranges(device_id,alarm_status,min_level,max_level) VALUES(?,?,?,?)', [$id,$range['alarm_status'],$range['min_level'],$range['max_level']]);
        db()->commit();
        return $id;
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        if ($e instanceof PDOException && $e->getCode() === '23000') fail('Device ID is already in use.');
        throw $e;
    } finally { query("SELECT RELEASE_LOCK('water_level_firmware')"); }
}

function uploadFirmware(array $input, array $file): void
{
    $version = validVersion($input['version'] ?? '');
    $description = $input['description'] ?? '';
    if (!is_string($description) || strlen($description) > 10000) fail('The changelog must not exceed 10,000 bytes.');
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) fail('Upload failed. Select a file and check PHP upload_max_filesize and post_max_size.');
    if (strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION)) !== 'bin') fail('The file must have a .bin extension.');
    $size = filesize($file['tmp_name']);
    if ($size < 1 || $size > 16 * 1024 * 1024) fail('Firmware size must be between 1 byte and 16 MB.');
    if (!is_uploaded_file($file['tmp_name'])) fail('Invalid upload source.');
    $path = null;
    if ((int) query("SELECT GET_LOCK('water_level_firmware',10)")->fetchColumn() !== 1) throw new RuntimeException('Could not lock firmware publication.');
    try {
        $latest = latestFirmware();
        if ($latest && version_compare(substr($version,1),substr($latest['version'],1),'<=') ) fail('The version must be higher than the latest global firmware.');
        $relative = 'storage/firmware/' . bin2hex(random_bytes(16)) . '.bin';
        $path = dirname(__DIR__) . '/' . $relative;
        if (!move_uploaded_file($file['tmp_name'], $path)) throw new RuntimeException('Could not save the firmware file.');
        $checksum = hash_file('sha256', $path);
        db()->beginTransaction();
        $time = now();
        query('INSERT INTO firmwares(version,file_path,description,file_size,sha256,created_at,updated_at) VALUES(?,?,?,?,?,?,?)',[$version,$relative,$description,$size,$checksum,$time,$time]);
        query('UPDATE devices SET current_firmware=?,updated_at=?',[$version,$time]);
        db()->commit();
        $path = null;
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        if ($path && is_file($path)) unlink($path);
        throw $e;
    } finally { query("SELECT RELEASE_LOCK('water_level_firmware')"); }
}

function deviceList(): array
{
    markOffline();
    $devices = query('SELECT * FROM devices ORDER BY id DESC')->fetchAll();
    $ranges = query('SELECT * FROM device_alert_ranges ORDER BY alarm_status')->fetchAll();
    $grouped = [];
    foreach ($ranges as $range) $grouped[$range['device_id']][] = $range;
    foreach ($devices as &$device) {
        $device['alert_status'] = $device['water_level'] === null ? null : classify((float)$device['water_level'], $grouped[$device['id']] ?? []);
        $device['alarm_active'] = $device['water_level'] !== null && alarmOn((float)$device['water_level'],(float)$device['alarm_threshold'],(bool)$device['buzzer_status']);
        $device['last_seen_display'] = displayTime($device['last_seen_at']);
    }
    return $devices;
}
