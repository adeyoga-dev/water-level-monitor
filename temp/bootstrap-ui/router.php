<?php
// Isolated view fixture: never loads bootstrap.php, .env, or a database connection.
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($path, '/assets/')) {
    return false;
}
require $root . '/app/core.php';
require $root . '/app/layout.php';
$GLOBALS['config'] = ['APP_TIMEZONE' => 'Asia/Jakarta'];
$empty = isset($_GET['empty']);
$device = [
    'id' => 1, 'device_id' => 'ESP32-001', 'device_status' => 1, 'water_level' => 25.5,
    'alarm_threshold' => 20, 'buzzer_status' => 1, 'update_interval' => 60,
    'reported_firmware' => 'v1.0.0', 'current_firmware' => 'v1.1.0',
    'last_seen_display' => '05 Nov 2026 22:05:00',
];
$devices = $empty ? [] : [$device + ['alert_status' => 1], array_replace($device, ['id' => 2, 'device_id' => 'ESP32-OFFLINE', 'device_status' => 0, 'water_level' => null, 'alert_status' => null])];
$online = $empty ? 0 : 1;
$logs = $empty ? 0 : 1234;
if ($path === '/api/dashboard.php') {
    ob_start();
    deviceTable($devices);
    $html = ob_get_clean();
    jsonResponse(['status' => 'success', 'html' => $html, 'total' => count($devices), 'online' => $online, 'logs' => $logs]);
}
$file = ['/' => 'index.php', '/devices.php' => 'devices.php', '/device-edit.php' => 'device-edit.php', '/firmwares.php' => 'firmwares.php', '/logs.php' => 'logs.php'][$path] ?? null;
if (!$file) {
    http_response_code(404);
    exit('Fixture not found.');
}
$id = isset($_GET['id']) ? 1 : 0;
$ranges = [['min_level' => null, 'max_level' => 20], ['min_level' => 20, 'max_level' => 40], ['min_level' => 40, 'max_level' => null]];
$values = $device + ['hours' => 0, 'minutes' => 1, 'seconds' => 0];
$error = isset($_GET['error']) ? 'Fixture validation error. Your input is preserved.' : null;
$page = max(1, (int) ($_GET['page'] ?? 1));
$pages = 2;
$count = $empty ? 0 : 26;
$firmwares = $empty ? [] : [[
    'id' => 1, 'version' => 'v1.1.0', 'description' => "Release notes <script>unsafe</script>\n" . str_repeat('LongUnbrokenReleaseNote', 20),
    'file_size' => 2048, 'created_at' => '2026-11-05 15:05:00',
]];
$latest = $firmwares[0] ?? false;
if ($file === 'logs.php') {
    $device = $_GET['device_id'] ?? '';
    $status = $_GET['alarm_status'] ?? '';
    $from = $_GET['from'] ?? '';
    $to = $_GET['to'] ?? '';
    $link = fn($p) => '?' . http_build_query(['device_id' => $device, 'alarm_status' => $status, 'from' => $from, 'to' => $to, 'page' => $p]);
    $logs = $empty ? [] : [
        ['id' => 1234, 'device_id' => 'ESP32-001', 'water_level' => 25.5, 'alarm_status' => 1, 'created_at' => '2026-11-05 15:05:00'],
        ['id' => 1233, 'device_id' => 'ESP32-001', 'water_level' => 15, 'alarm_status' => 0, 'created_at' => '2026-11-05 15:04:00'],
        ['id' => 1232, 'device_id' => 'ESP32-001', 'water_level' => 45, 'alarm_status' => 2, 'created_at' => '2026-11-05 15:03:00'],
        ['id' => 1231, 'device_id' => 'ESP32-001', 'water_level' => -1, 'alarm_status' => null, 'created_at' => '2026-11-05 15:02:00'],
    ];
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = 'Fixture validation error. Your input is preserved.';
    $values = $_POST;
    $ranges = $_POST['ranges'] ?? $ranges;
}
startSession();
if (isset($_GET['success'])) {
    $_SESSION['flash'] = 'Fixture settings saved successfully.';
}
$source = file_get_contents($root . '/public/' . $file);
$start = strpos($source, "\npageStart(");
if ($start === false) {
    throw new RuntimeException('View entry point not found.');
}
eval(substr($source, $start));
