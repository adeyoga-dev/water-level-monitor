<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/core.php';
require dirname(__DIR__, 2) . '/app/admin.php';
$expectedData = getenv('HISTORY_TEST_DATADIR');
$database = config('DB_DATABASE');
if (!$expectedData || config('DB_HOST') !== '127.0.0.1' || config('DB_PORT') === '3306' || !preg_match('/^water_level_test_history_[a-f0-9]{12}$/D', $database)) {
    throw new RuntimeException('An explicitly isolated history test database is required.');
}
$pdo = new PDO('mysql:host=127.0.0.1;port=' . config('DB_PORT') . ';charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$actualData = $pdo->query('SELECT @@datadir')->fetchColumn();
if (strcasecmp(rtrim(str_replace('\\', '/', $actualData), '/'), rtrim(str_replace('\\', '/', $expectedData), '/')) !== 0) {
    throw new RuntimeException('The database server is not the owned temporary instance.');
}
if (in_array('--verify-server', $argv, true)) {
    echo "Isolated database server verified.\n";
    exit;
}
$pdo->exec('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4');
$pdo->exec('USE `' . $database . '`');
$schema = file_get_contents(dirname(__DIR__, 2) . '/database/schema.sql');
$schema = preg_replace('/^CREATE DATABASE[^;]+;\s*USE[^;]+;/', '', $schema);
foreach (explode(';', $schema) as $statement) {
    if (trim($statement) !== '') {
        $pdo->exec($statement);
    }
}

$ranges = [['min_level' => null, 'max_level' => 19], ['min_level' => 20, 'max_level' => 39], ['min_level' => 40, 'max_level' => null]];
foreach (['ESP32-001', 'ESP32-002', 'ESP32-EMPTY', 'ESP32-SINGLE', 'ESP32-PERIOD'] as $identifier) {
    saveDevice(['device_id' => $identifier, 'alarm_threshold' => 30, 'hours' => 0, 'minutes' => 1, 'seconds' => 0, 'buzzer_status' => '1', 'ranges' => $ranges]);
}
query('UPDATE devices SET latitude=?,longitude=? WHERE device_id=?', [-7.566712, 110.833458, 'ESP32-001']);
query('UPDATE devices SET latitude=?,longitude=? WHERE device_id=?', [0, 0, 'ESP32-002']);
$time = time();
$dayStart = (new DateTimeImmutable('today', new DateTimeZone('Asia/Jakarta')))->getTimestamp();
db()->beginTransaction();
for ($index = 0; $index < 80; $index++) {
    $level = round(25 + 13 * sin($index / 10) + $index / 10, 2);
    query('INSERT INTO sensor_logs(device_id,water_level,alarm_status,created_at) VALUES(?,?,?,?)', ['ESP32-001', $level, classify($level, validateRanges($ranges)), gmdate('Y-m-d H:i:s', max($dayStart, $time - (79 - $index) * 60))]);
}
query('INSERT INTO sensor_logs(device_id,water_level,alarm_status,created_at) VALUES(?,?,?,?)', ['ESP32-002', 99, 2, gmdate('Y-m-d H:i:s', $time)]);
query('INSERT INTO sensor_logs(device_id,water_level,alarm_status,created_at) VALUES(?,?,?,?)', ['ESP32-SINGLE', 21.5, 1, gmdate('Y-m-d H:i:s', $time)]);
query('UPDATE devices SET water_level=?,device_status=1,last_seen_at=? WHERE device_id=?', [$level, gmdate('Y-m-d H:i:s', $time), 'ESP32-001']);
query('UPDATE devices SET water_level=99,device_status=1,last_seen_at=? WHERE device_id=?', [gmdate('Y-m-d H:i:s', $time), 'ESP32-002']);
query('UPDATE device_alert_ranges SET max_level=50 WHERE device_id=(SELECT id FROM devices WHERE device_id=?) AND alarm_status=2', ['ESP32-002']);
$cursor = new DateTimeImmutable('2024-01-01 00:00:00', new DateTimeZone('Asia/Jakarta'));
while ($cursor->format('Y') < '2027') {
    foreach ([8 => -6, 16 => 6] as $hour => $variation) {
        $level = round(42 + ((int) $cursor->format('Y') - 2024) * 3 + 12 * sin((int) $cursor->format('z') / 25) + $variation, 2);
        query('INSERT INTO sensor_logs(device_id,water_level,alarm_status,created_at) VALUES(?,?,?,?)', ['ESP32-PERIOD', $level, classify($level, validateRanges($ranges)), gmdate('Y-m-d H:i:s', $cursor->modify('+' . $hour . ' hours')->getTimestamp())]);
    }
    $cursor = $cursor->modify('+1 day');
}
db()->commit();
// Exercise the historical migration and cleanup only on this verified temporary server.
$businessTables = ['devices', 'device_alert_ranges', 'sensor_logs', 'firmwares'];
$beforeCleanup = [];
foreach ($businessTables as $table) $beforeCleanup[$table] = query('SELECT * FROM `' . $table . '`')->fetchAll();
$authSchema = file_get_contents(dirname(__DIR__, 2) . '/database/migrations/003_auth.sql');
foreach (explode(';', $authSchema) as $statement) {
    if (trim($statement) !== '') $pdo->exec($statement);
}
$argv[] = '--apply';
require dirname(__DIR__, 2) . '/scripts/cleanup-auth.php';
foreach ($businessTables as $table) {
    if (query('SELECT * FROM `' . $table . '`')->fetchAll() !== $beforeCleanup[$table]) throw new RuntimeException('Cleanup changed a business table.');
}
if (query("SHOW TABLES LIKE 'users%'")->fetch()) throw new RuntimeException('Authentication tables remain after cleanup.');

echo "Isolated browser seed ready.\n";
