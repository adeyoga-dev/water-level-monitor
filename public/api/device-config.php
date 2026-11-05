<?php
require dirname(__DIR__, 2) . '/app/bootstrap.php';
api(function (): array {
    $id = $_GET['device_id'] ?? '';
    if (!is_string($id) || !preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $id)) fail('Invalid device_id.');
    $device = query('SELECT * FROM devices WHERE device_id=?', [$id])->fetch();
    if (!$device) throw new OutOfBoundsException('Device is not registered.');
    return deviceConfig($device);
}, 'GET');
