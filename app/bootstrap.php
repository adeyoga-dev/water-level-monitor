<?php

declare(strict_types=1);

require_once __DIR__ . '/core.php';
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', dirname(__DIR__) . '/storage/logs/php.log');
set_exception_handler(function (Throwable $e): void {
    error_log((string) $e);
    http_response_code(500);
    if (str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => 'A server error occurred. Check the configuration and server logs.']);
    } else {
        echo '<h1>Aplikasi tidak dapat memproses permintaan ini</h1><p>Periksa koneksi MySQL, impor schema.sql, dan konfigurasi .env. Detail tersedia di storage/logs/php.log.</p>';
    }
});
loadEnv(dirname(__DIR__) . '/.env');
date_default_timezone_set('UTC');
if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/auth.php';
    $path = $_SERVER['SCRIPT_NAME'] ?? '';
    $public = ['/login.php', '/viewer.php', '/viewer-maps.php', '/api/viewer-dashboard.php', '/api/telemetry.php', '/api/device-config.php', '/api/firmware-download.php'];
    if (!in_array($path, $public, true)) requireAccess();
}
