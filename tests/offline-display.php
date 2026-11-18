<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/core.php';
require dirname(__DIR__) . '/app/layout.php';

function verifyDisplay(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo 'PASS ' . $message . "\n";
}

$device = [
    'id' => 1,
    'device_id' => 'DISPLAY-TEST',
    'station_name' => 'Display test',
    'device_status' => 0,
    'water_level' => 520.0,
    'last_seen_at' => gmdate('Y-m-d H:i:s'),
    'update_interval' => 60,
    'alert_ranges' => [
        ['alarm_status' => 0, 'min_level' => 100, 'max_level' => 199],
        ['alarm_status' => 1, 'min_level' => 200, 'max_level' => 299],
        ['alarm_status' => 2, 'min_level' => 300, 'max_level' => null],
    ],
    'alert_status' => 2,
    'buzzer_status' => 0,
    'alarm_threshold' => 500,
    'last_seen_display' => 'Test timestamp',
];
$original = $device;
verifyDisplay(displayWaterLevel($device) === 0.0, 'Disconnected reading displays zero');
foreach ([false, true] as $viewer) {
    ob_start();
    waterLevelPanels([$device], false, $viewer);
    $html = ob_get_clean();
    verifyDisplay(str_contains($html, '<strong class="h2">0.0</strong>'), 'Panel displays zero for ' . ($viewer ? 'viewer' : 'admin'));
    verifyDisplay(str_contains($html, 'badge-secondary">Tidak terklasifikasi</span>') && str_contains($html, 'data-alert-status=""'), 'Zero uses master ranges and clears the stale alert for ' . ($viewer ? 'viewer' : 'admin'));
    verifyDisplay(str_contains($html, $viewer ? '>Terhubung</span>' : '>Terputus</span>'), 'Connection badge preserved for ' . ($viewer ? 'viewer' : 'admin'));
}
ob_start();
deviceTable([$device]);
$html = ob_get_clean();
verifyDisplay(str_contains($html, '<strong>0.00</strong>'), 'Device table displays zero');
verifyDisplay(str_contains($html, 'badge-secondary">Tidak terklasifikasi</span>'), 'Device table classifies displayed zero');
verifyDisplay($device === $original, 'Rendering preserves source reading and status');

$device['device_status'] = 1;
verifyDisplay(displayWaterLevel($device) === 520.0, 'Connected reading stays visible');
$device['water_level'] = 100;
ob_start();
waterLevelPanels([$device], true);
$html = ob_get_clean();
verifyDisplay(str_contains($html, '<strong class="h2">100.0</strong>') && str_contains($html, 'data-alert-status="0"'), 'Reconnected reading at 100 cm displays Siaga 1 from master ranges');
$device['water_level'] = 520.0;
$device['last_seen_at'] = gmdate('Y-m-d H:i:s', time() - 181);
verifyDisplay(displayWaterLevel($device) === 0.0, 'Stale heartbeat displays zero without an admin request');
ob_start();
waterLevelPanels([$device], false, true);
$html = ob_get_clean();
verifyDisplay(str_contains($html, '<strong class="h2">0.0</strong>') && str_contains($html, '>Terhubung</span>'), 'Stale viewer displays zero with connected badge');
$device['update_interval'] = 1;
$device['last_seen_at'] = gmdate('Y-m-d H:i:s', time() - 20);
verifyDisplay(displayWaterLevel($device) === 520.0, 'Minimum heartbeat grace period is 30 seconds');
$device['last_seen_at'] = gmdate('Y-m-d H:i:s', time() - 31);
verifyDisplay(displayWaterLevel($device) === 0.0, 'Minimum grace period expires');
$device['last_seen_at'] = null;
$device['water_level'] = null;
verifyDisplay(displayWaterLevel($device) === 0.0, 'Never connected device displays zero');
$device['last_seen_at'] = gmdate('Y-m-d H:i:s');
verifyDisplay(displayWaterLevel($device) === null, 'Connected device with no reading keeps empty state');
