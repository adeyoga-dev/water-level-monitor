<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/core.php';
require dirname(__DIR__) . '/app/admin.php';
$expectedData = getenv('HISTORY_TEST_DATADIR');
if (!$expectedData || config('DB_HOST') !== '127.0.0.1' || config('DB_PORT') === '3306' || !preg_match('/^water_level_test_history_[a-f0-9]{12}$/D', config('DB_DATABASE'))) {
    throw new RuntimeException('An owned isolated test database is required.');
}
$actualData = query('SELECT @@datadir')->fetchColumn();
if (strcasecmp(rtrim(str_replace('\\', '/', $actualData), '/'), rtrim(str_replace('\\', '/', $expectedData), '/')) !== 0) throw new RuntimeException('Unexpected database server.');
function verifyReading(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException($message);
}
function sendReading(array $input): int
{
    $client = curl_init(config('APP_URL') . '/api/telemetry.php');
    curl_setopt_array($client, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($input), CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
    curl_exec($client);
    $code = curl_getinfo($client, CURLINFO_RESPONSE_CODE);
    curl_close($client);
    return $code;
}
$beforeMigration = [];
foreach (['devices', 'sensor_logs'] as $table) {
    $beforeMigration[$table] = array_map(fn($row) => array_diff_key($row, ['sensor_distance' => true]), query("SELECT * FROM `$table` ORDER BY id")->fetchAll());
    query("ALTER TABLE `$table` DROP COLUMN sensor_distance");
}
$argv[] = '--apply';
require dirname(__DIR__) . '/scripts/migrate-sensor-distance.php';
restore_exception_handler();
foreach (['devices', 'sensor_logs'] as $table) {
    $rows = query("SELECT * FROM `$table` ORDER BY id")->fetchAll();
    verifyReading($beforeMigration[$table] === array_map(fn($row) => array_diff_key($row, ['sensor_distance' => true]), $rows), 'Migration changed existing readings.');
    verifyReading(count(array_filter($rows, fn($row) => $row['sensor_distance'] !== null)) === 0, 'Migration invented distances.');
}
$input = ['device_id' => 'ESP32-001', 'water_level' => 320, 'measurement_type' => 'water_height', 'device_status' => 1, 'firmware_version' => 'v1.0.1'];
verifyReading(sendReading($input) === 200, 'Height-only reading rejected.');
$device = query('SELECT * FROM devices WHERE device_id=?', [$input['device_id']])->fetch();
$log = query('SELECT * FROM sensor_logs WHERE device_id=? ORDER BY id DESC LIMIT 1', [$input['device_id']])->fetch();
verifyReading((float) $device['water_level'] === 320.0 && (float) $log['water_level'] === 320.0 && $log['sensor_distance'] === null, 'Height not persisted or a distance was invented.');
verifyReading((int) $log['alarm_status'] === 2, 'Siaga must use height.');
$before = query('SELECT COUNT(*) FROM sensor_logs')->fetchColumn();
foreach ([-1, null, [], 'bad'] as $invalidHeight) {
    $bad = $input;
    $bad['water_level'] = $invalidHeight;
    verifyReading(sendReading($bad) === 422, 'Invalid height accepted.');
}
$bad = $input;
$bad['measurement_type'] = 'sensor_distance';
verifyReading(sendReading($bad) === 422, 'Distance mode accepted as height.');
verifyReading(query('SELECT COUNT(*) FROM sensor_logs')->fetchColumn() === $before, 'Invalid requests wrote logs.');
$old = $input;
unset($old['measurement_type']);
verifyReading(sendReading($old) === 200, 'Legacy height request rejected.');
query("CREATE TRIGGER sensor_pair_failure BEFORE INSERT ON sensor_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='isolated test failure'");
try {
    $bad = $input;
    $bad['water_level'] = 600;
    verifyReading(sendReading($bad) === 500, 'Failed log insertion was not rejected.');
    verifyReading((float) query('SELECT water_level FROM devices WHERE device_id=?', [$input['device_id']])->fetchColumn() === 320.0, 'Height was not rolled back.');
} finally {
    query('DROP TRIGGER sensor_pair_failure');
}
$history = deviceHistory('ESP32-001');
verifyReading((float) end($history['points'])['y'] === 320.0, 'History lost height.');
verifyReading(!alarmOn(300, 300, true) && alarmOn(320, 300, true), 'Height alarm boundary differs.');
echo "Height-only HTTP validation, persistence, legacy compatibility, rollback and history checks passed.\n";
