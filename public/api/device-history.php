<?php
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require dirname(__DIR__, 2) . '/app/admin.php';
require dirname(__DIR__, 2) . '/app/layout.php';
api(function (): array {
    $history = deviceHistory($_GET['device_id'] ?? null, $_GET);
    ob_start();
    waterLevelPanels([$history['device']], true);
    $html = ob_get_clean();
    return ['status' => 'success', ...$history, 'html' => $html];
}, 'GET');
