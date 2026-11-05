<?php
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require dirname(__DIR__, 2) . '/app/admin.php';
require dirname(__DIR__, 2) . '/app/layout.php';
api(function (): array {
    $devices = deviceList();
    ob_start();
    waterLevelPanels($devices);
    $html = ob_get_clean();
    return ['status' => 'success', 'html' => $html, 'total' => count($devices), 'online' => count(array_filter($devices, fn($d) => (int)$d['device_status'] === 1)), 'logs' => (int)query('SELECT COUNT(*) FROM sensor_logs')->fetchColumn()];
}, 'GET');
