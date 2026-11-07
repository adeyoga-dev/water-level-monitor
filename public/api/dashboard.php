<?php
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require dirname(__DIR__, 2) . '/app/admin.php';
require dirname(__DIR__, 2) . '/app/layout.php';
api(function (): array {
    $viewer = defined('FLOW_VIEWER') && FLOW_VIEWER;
    $devices = deviceList(!$viewer);
    ob_start();
    waterLevelPanels($devices, false, $viewer);
    $html = ob_get_clean();
    if ($viewer) return ['status' => 'success', 'html' => $html, 'total' => count($devices), 'online' => count($devices)];
    return ['status' => 'success', 'html' => $html, 'total' => count($devices), 'online' => count(array_filter($devices, fn($d) => (int)$d['device_status'] === 1)), 'logs' => (int)query('SELECT COUNT(*) FROM sensor_logs')->fetchColumn()];
}, 'GET');
