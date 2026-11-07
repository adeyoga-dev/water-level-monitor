<?php

declare(strict_types=1);

/** @return array{0: ?float, 1: ?float} Latitude and longitude, or an empty location. */
function parseCoordinates(mixed $value): array
{
    if (!is_string($value) || strlen($value) > 100) {
        fail('Coordinates must be a latitude, longitude pair, for example -7.566712, 110.833458.');
    }
    $value = trim($value);
    if ($value === '') {
        return [null, null];
    }
    if (!preg_match('/^([+-]?(?:\d+(?:\.\d+)?|\.\d+))\s*,\s*([+-]?(?:\d+(?:\.\d+)?|\.\d+))$/D', $value, $matches)) {
        fail('Paste numeric latitude, longitude coordinates, not a Google Maps share link.');
    }
    $latitude = finiteNumber($matches[1], 'Latitude');
    $longitude = finiteNumber($matches[2], 'Longitude');
    if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
        fail('Latitude must be between -90 and 90; longitude must be between -180 and 180.');
    }
    return [round($latitude, 7), round($longitude, 7)];
}

function saveDevice(array $input): int
{
    $id = filter_var($input['id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    if ($id === false) fail('Invalid ID.');
    $identifier = $input['device_id'] ?? '';
    if (!is_string($identifier) || !preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $identifier)) fail('Device ID must contain 1–64 letters, numbers, hyphens, or underscores.');
    $threshold = finiteNumber($input['alarm_threshold'] ?? null, 'Threshold');
    $parts = [];
    foreach (['hours' => 23, 'minutes' => 59, 'seconds' => 59] as $key => $max) {
        $value = filter_var($input[$key] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => $max]]);
        if ($value === false) fail('Duration must use hours 0–23, minutes and seconds 0–59.');
        $parts[$key] = $value;
    }
    $interval = $parts['hours'] * 3600 + $parts['minutes'] * 60 + $parts['seconds'];
    if ($interval < 1) fail('The minimum interval is 1 second.');
    $ranges = validateRanges($input['ranges'] ?? []);
    $enabled = isset($input['buzzer_status']) ? 1 : 0;
    $coordinates = array_key_exists('coordinates', $input) ? parseCoordinates($input['coordinates']) : null;
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
            $coordinates ??= [$existing['latitude'], $existing['longitude']];
            query('UPDATE devices SET update_interval=?,alarm_threshold=?,buzzer_status=?,latitude=?,longitude=?,updated_at=? WHERE id=?', [$interval, $threshold, $enabled, $coordinates[0], $coordinates[1], $time, $id]);
            query('DELETE FROM device_alert_ranges WHERE device_id=?', [$id]);
        } else {
            $coordinates ??= [null, null];
            query('INSERT INTO devices(device_id,update_interval,alarm_threshold,buzzer_status,latitude,longitude,current_firmware,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)', [$identifier, $interval, $threshold, $enabled, $coordinates[0], $coordinates[1], $firmware ? $firmware['version'] : null, $time, $time]);
            $id = (int) db()->lastInsertId();
        }
        foreach ($ranges as $range) query('INSERT INTO device_alert_ranges(device_id,alarm_status,min_level,max_level) VALUES(?,?,?,?)', [$id, $range['alarm_status'], $range['min_level'], $range['max_level']]);
        db()->commit();
        return $id;
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        if ($e instanceof PDOException && $e->getCode() === '23000') fail('Device ID is already in use.');
        throw $e;
    } finally {
        query("SELECT RELEASE_LOCK('water_level_firmware')");
    }
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
        if ($latest && version_compare(substr($version, 1), substr($latest['version'], 1), '<=')) fail('The version must be higher than the latest global firmware.');
        $relative = 'storage/firmware/' . bin2hex(random_bytes(16)) . '.bin';
        $path = dirname(__DIR__) . '/' . $relative;
        if (!move_uploaded_file($file['tmp_name'], $path)) throw new RuntimeException('Could not save the firmware file.');
        $checksum = hash_file('sha256', $path);
        db()->beginTransaction();
        $time = now();
        query('INSERT INTO firmwares(version,file_path,description,file_size,sha256,created_at,updated_at) VALUES(?,?,?,?,?,?,?)', [$version, $relative, $description, $size, $checksum, $time, $time]);
        query('UPDATE devices SET current_firmware=?,updated_at=?', [$version, $time]);
        db()->commit();
        $path = null;
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        if ($path && is_file($path)) unlink($path);
        throw $e;
    } finally {
        query("SELECT RELEASE_LOCK('water_level_firmware')");
    }
}

function deviceList(bool $refreshStatus = true): array
{
    if ($refreshStatus) markOffline();
    $devices = query('SELECT * FROM devices ORDER BY id DESC')->fetchAll();
    $ranges = query('SELECT * FROM device_alert_ranges ORDER BY alarm_status')->fetchAll();
    $grouped = [];
    foreach ($ranges as $range) $grouped[$range['device_id']][] = $range;
    foreach ($devices as &$device) {
        $device['alert_ranges'] = $grouped[$device['id']] ?? [];
        $device['alert_status'] = $device['water_level'] === null ? null : classify((float)$device['water_level'], $grouped[$device['id']] ?? []);
        $device['alarm_active'] = $device['water_level'] !== null && alarmOn((float)$device['water_level'], (float)$device['alarm_threshold'], (bool)$device['buzzer_status']);
        $device['last_seen_display'] = displayTime($device['last_seen_at']);
    }
    return $devices;
}

/** Returns raw recent readings or calendar aggregates for an explicitly selected local date range. */
function deviceHistory(mixed $identifier, array $filters = []): array
{
    if (!is_string($identifier) || !preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $identifier)) {
        fail('Invalid device_id.');
    }
    $timezone = config('APP_TIMEZONE', 'Asia/Jakarta');
    $zone = new DateTimeZone($timezone);
    $filtered = array_key_exists('from', $filters) || array_key_exists('to', $filters) || array_key_exists('group_by', $filters);
    $aggregation = 'raw';
    $selectedFilters = null;
    $period = null;
    if ($filtered) {
        foreach (['from', 'to'] as $field) {
            $value = $filters[$field] ?? null;
            if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
                fail('Provide valid From and To dates in YYYY-MM-DD format.');
            }
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $zone);
            if (!$date || $date->format('Y-m-d') !== $value || (int) $date->format('Y') < 1000 || $value > '9999-12-30') {
                fail('Provide valid From and To dates in YYYY-MM-DD format.');
            }
        }
        $fromDate = new DateTimeImmutable($filters['from'] . ' 00:00:00', $zone);
        $toDate = new DateTimeImmutable($filters['to'] . ' 00:00:00', $zone);
        if ($fromDate > $toDate) {
            fail('From date must be on or before To date.');
        }
        $groupBy = $filters['group_by'] ?? 'auto';
        if (!is_string($groupBy) || !in_array($groupBy, ['auto', 'minute', 'hour', 'day', 'month', 'year'], true)) {
            fail('Choose Auto, Minute, Hour, Day, Month, or Year grouping.');
        }
        $days = (int) $fromDate->diff($toDate)->days + 1;
        $aggregation = $groupBy === 'auto' ? ($days === 1 ? 'hour' : ($days <= 62 ? 'day' : ($days <= 731 ? 'month' : 'year'))) : $groupBy;
        if ($aggregation === 'minute' && $days !== 1) {
            fail('Minute grouping requires a single date. Set From date and To date to the same date.');
        }
        $endDate = $toDate->modify('+1 day');
        $utc = new DateTimeZone('UTC');
        $startUtc = $fromDate->setTimezone($utc);
        $endUtc = $endDate->setTimezone($utc);
        $bucketCount = match ($aggregation) {
            'minute' => (int) ceil(($endUtc->getTimestamp() - $startUtc->getTimestamp()) / 60),
            'hour' => (int) ceil(($endUtc->getTimestamp() - $startUtc->getTimestamp()) / 3600),
            'day' => $days,
            'month' => ((int) $toDate->format('Y') - (int) $fromDate->format('Y')) * 12 + (int) $toDate->format('n') - (int) $fromDate->format('n') + 1,
            'year' => (int) $toDate->format('Y') - (int) $fromDate->format('Y') + 1,
        };
        $maxPoints = $aggregation === 'minute' ? 1500 : 500;
        if ($bucketCount > $maxPoints) {
            fail('This grouping would create more than ' . $maxPoints . ' points. Shorten the date range or choose a larger grouping.');
        }
        $selectedFilters = ['from' => $filters['from'], 'to' => $filters['to'], 'group_by' => $groupBy];
        $period = ['from' => $startUtc->getTimestamp() * 1000, 'to' => $endUtc->getTimestamp() * 1000 - 1];
    }
    $device = query('SELECT * FROM devices WHERE device_id=?', [$identifier])->fetch();
    if (!$device) {
        throw new OutOfBoundsException('Device is not registered.');
    }
    $device['alert_ranges'] = query('SELECT * FROM device_alert_ranges WHERE device_id=? ORDER BY alarm_status', [$device['id']])->fetchAll();
    $device['alert_status'] = $device['water_level'] === null ? null : classify((float) $device['water_level'], $device['alert_ranges']);
    $device['last_seen_display'] = displayTime($device['last_seen_at']);
    // History requests only read data; evaluate heartbeat expiry without updating devices.
    if ($device['device_status']) {
        $lastSeen = $device['last_seen_at'] === null ? null : new DateTimeImmutable($device['last_seen_at'], new DateTimeZone('UTC'));
        if (!$lastSeen || time() - $lastSeen->getTimestamp() > max(3 * (int) $device['update_interval'], 30)) {
            $device['device_status'] = 0;
        }
    }
    $points = [];
    $readingCount = 0;
    if (!$filtered) {
        $logs = query('SELECT water_level,alarm_status,created_at FROM sensor_logs WHERE device_id=? ORDER BY created_at DESC,id DESC LIMIT 500', [$identifier])->fetchAll();
        foreach (array_reverse($logs) as $log) {
            $points[] = [
                'x' => (new DateTimeImmutable($log['created_at'], new DateTimeZone('UTC')))->getTimestamp() * 1000,
                'y' => (float) $log['water_level'],
                'alarm_status' => $log['alarm_status'] === null ? null : (int) $log['alarm_status'],
            ];
        }
        $readingCount = count($points);
    } else {
        // Compute offsets in PHP so calendar grouping does not depend on MySQL timezone tables.
        $offsetSql = '';
        $offsetParams = [];
        $transitions = $zone->getTransitions($startUtc->getTimestamp(), $endUtc->getTimestamp()) ?: [];
        foreach (array_reverse(array_slice($transitions, 1)) as $transition) {
            $offsetSql .= 'WHEN created_at >= ? THEN ? ';
            array_push($offsetParams, gmdate('Y-m-d H:i:s', $transition['ts']), $transition['offset']);
        }
        $offsetSql = $offsetSql === '' ? '?' : 'CASE ' . $offsetSql . 'ELSE ? END';
        $offsetParams[] = $zone->getOffset($startUtc);
        $format = match ($aggregation) {
            'minute' => '%Y-%m-%d %H:%i:00',
            'hour' => '%Y-%m-%d %H:00:00',
            'day' => '%Y-%m-%d 00:00:00',
            'month' => '%Y-%m-01 00:00:00',
            'year' => '%Y-01-01 00:00:00',
        };
        $subDaily = in_array($aggregation, ['minute', 'hour'], true);
        $bucketOffset = $subDaily ? ',utc_offset' : '';
        $rows = query(
            "SELECT DATE_FORMAT(TIMESTAMPADD(SECOND,utc_offset,created_at),'$format') AS bucket$bucketOffset, " .
            'AVG(water_level) AS average_level, MIN(water_level) AS min_level, MAX(water_level) AS max_level, COUNT(*) AS reading_count ' .
            "FROM (SELECT created_at,water_level,CAST(($offsetSql) AS SIGNED) AS utc_offset FROM sensor_logs " .
            "WHERE device_id=? AND created_at>=? AND created_at<?) AS history GROUP BY bucket$bucketOffset ORDER BY bucket ASC" .
            ($subDaily ? ',utc_offset DESC' : ''),
            [...$offsetParams, $identifier, $startUtc->format('Y-m-d H:i:s'), $endUtc->format('Y-m-d H:i:s')]
        )->fetchAll();
        $buckets = [];
        foreach ($rows as $row) {
            $key = $row['bucket'] . ($subDaily ? '|' . $row['utc_offset'] : '');
            $buckets[$key] = $row;
            $readingCount += (int) $row['reading_count'];
        }
        $cursor = match ($aggregation) {
            'minute', 'hour' => $startUtc,
            'day' => $fromDate,
            'month' => $fromDate->modify('first day of this month'),
            'year' => $fromDate->setDate((int) $fromDate->format('Y'), 1, 1),
        };
        while ($cursor < $endDate) {
            $local = $cursor->setTimezone($zone);
            $keyFormat = match ($aggregation) {
                'minute' => 'Y-m-d H:i:00',
                'hour' => 'Y-m-d H:00:00',
                default => 'Y-m-d 00:00:00',
            };
            $key = $local->format($keyFormat) . ($subDaily ? '|' . $zone->getOffset($cursor) : '');
            $row = $buckets[$key] ?? null;
            $points[] = [
                'x' => max($cursor->getTimestamp() * 1000, $period['from']),
                'y' => $row ? (float) $row['average_level'] : null,
                'min' => $row ? (float) $row['min_level'] : null,
                'max' => $row ? (float) $row['max_level'] : null,
                'count' => $row ? (int) $row['reading_count'] : 0,
                'alarm_status' => null,
            ];
            $cursor = $cursor->modify('+1 ' . $aggregation);
        }
    }
    return ['device' => $device, 'points' => $points, 'timezone' => $timezone, 'limit' => $filtered ? null : 500, 'filters' => $selectedFilters, 'aggregation' => $aggregation, 'period' => $period, 'reading_count' => $readingCount];
}
