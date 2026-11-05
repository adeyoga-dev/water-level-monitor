<?php

declare(strict_types=1);

function parseEnv(string $source): array
{
    $values = [];
    foreach (preg_split('/\R/', $source) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (!preg_match('/^([A-Z][A-Z0-9_]*)\s*=(.*)$/', $line, $match)) {
            throw new RuntimeException('Invalid .env format.');
        }
        $value = trim($match[2]);
        if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
            $quote = $value[0];
            if (strlen($value) < 2 || !str_ends_with($value, $quote)) throw new RuntimeException('Quoted .env values must have a closing quote.');
            $value = substr($value, 1, -1);
        }
        $values[$match[1]] = $value;
    }
    return $values;
}

function loadEnv(string $path): void
{
    $GLOBALS['config'] = is_file($path) ? parseEnv((string) file_get_contents($path)) : [];
}

function config(string $key, string $default = ''): string
{
    $environment = getenv($key);
    return $environment !== false ? $environment : ($GLOBALS['config'][$key] ?? $default);
}

function db(): PDO
{
    static $pdo;
    if (!$pdo) {
        $charset = config('DB_CHARSET', 'utf8mb4');
        if ($charset !== 'utf8mb4') throw new RuntimeException('DB_CHARSET must be utf8mb4.');
        $pdo = new PDO(
            'mysql:host=' . config('DB_HOST', '127.0.0.1') . ';port=' . config('DB_PORT', '3306') . ';dbname=' . config('DB_DATABASE', 'water_level_detection') . ';charset=' . $charset,
            config('DB_USERNAME', 'root'),
            config('DB_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
        );
        $pdo->exec("SET time_zone = '+00:00'");
    }
    return $pdo;
}

function query(string $sql, array $params = []): PDOStatement
{
    $statement = db()->prepare($sql);
    $statement->execute($params);
    return $statement;
}

function h(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}
function now(): string
{
    return gmdate('Y-m-d H:i:s');
}
function displayTime(?string $value): string
{
    return $value ? (new DateTimeImmutable($value, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone(config('APP_TIMEZONE', 'Asia/Jakarta')))->format('d M Y H:i:s') : 'Never connected';
}
function fail(string $message): never
{
    throw new InvalidArgumentException($message);
}
function finiteNumber(mixed $value, string $label): float
{
    if ((!is_string($value) && !is_int($value) && !is_float($value)) || !is_numeric($value) || !is_finite((float) $value)) fail($label . ' must be a finite number.');
    return (float) $value;
}
function validVersion(mixed $value): string
{
    if (!is_string($value) || !preg_match('/^v(0|[1-9]\d{0,8})\.(0|[1-9]\d{0,8})\.(0|[1-9]\d{0,8})$/D', $value)) fail('Version must use the v1.2.3 format.');
    return $value;
}
function validateRanges(array $ranges): array
{
    if (count($ranges) !== 3) fail('Provide all three alert ranges.');
    $result = [];
    foreach (array_values($ranges) as $status => $range) {
        $min = ($range['min_level'] ?? '') === '' || ($range['min_level'] ?? null) === null ? null : finiteNumber($range['min_level'], 'Lower bound');
        $max = ($range['max_level'] ?? '') === '' || ($range['max_level'] ?? null) === null ? null : finiteNumber($range['max_level'], 'Upper bound');
        if ($min !== null && $max !== null && $min >= $max) fail('The lower bound must be less than the upper bound.');
        $result[] = ['alarm_status' => $status, 'min_level' => $min, 'max_level' => $max];
    }
    for ($a = 0; $a < 3; $a++) for ($b = $a + 1; $b < 3; $b++) {
        if (max($result[$a]['min_level'] ?? -INF, $result[$b]['min_level'] ?? -INF) < min($result[$a]['max_level'] ?? INF, $result[$b]['max_level'] ?? INF)) fail('Alert ranges must not overlap.');
    }
    return $result;
}
function classify(float $level, array $ranges): ?int
{
    foreach ($ranges as $range) {
        if (($range['min_level'] === null || $level >= (float) $range['min_level']) && ($range['max_level'] === null || $level < (float) $range['max_level'])) return (int) $range['alarm_status'];
    }
    return null;
}
function alertLabel(mixed $status): string
{
    return $status === null ? 'Unclassified' : 'Siaga ' . ((int) $status + 1);
}
function alarmOn(float $water, float $threshold, bool $enabled): bool
{
    return $enabled && $water > $threshold;
}
function markOffline(): int
{
    return query('UPDATE devices SET device_status=0 WHERE device_status=1 AND (last_seen_at IS NULL OR TIMESTAMPDIFF(SECOND,last_seen_at,UTC_TIMESTAMP()) > GREATEST(3 * update_interval,30))')->rowCount();
}
function latestFirmware(): array|false
{
    return query('SELECT * FROM firmwares ORDER BY id DESC LIMIT 1')->fetch();
}
function deviceConfig(array $device): array
{
    $firmware = latestFirmware();
    return [
        'status' => 'success',
        'alarm_threshold' => (float) $device['alarm_threshold'],
        'buzzer_status' => (int) $device['buzzer_status'],
        'latest_firmware' => $firmware ? $firmware['version'] : null,
        'ota_url' => $firmware ? rtrim(config('APP_URL', 'http://localhost:8000'), '/') . '/api/firmware-download.php?id=' . $firmware['id'] : null,
        'firmware_sha256' => $firmware ? $firmware['sha256'] : null,
        'update_interval' => (int) $device['update_interval']
    ];
}
function telemetry(array $input): array
{
    $identifier = $input['device_id'] ?? null;
    if (!is_string($identifier) || !preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $identifier)) fail('Invalid device_id.');
    $level = finiteNumber($input['water_level'] ?? null, 'water_level');
    if (!isset($input['device_status']) || !in_array($input['device_status'], [0, 1], true)) fail('device_status must be the integer 0 or 1.');
    $version = array_key_exists('firmware_version', $input) ? validVersion($input['firmware_version']) : null;
    db()->beginTransaction();
    try {
        $device = query('SELECT * FROM devices WHERE device_id=? FOR UPDATE', [$identifier])->fetch();
        if (!$device) throw new OutOfBoundsException('Device is not registered.');
        $ranges = query('SELECT * FROM device_alert_ranges WHERE device_id=? ORDER BY alarm_status', [$device['id']])->fetchAll();
        $time = now();
        query('UPDATE devices SET water_level=?, device_status=?, reported_firmware=COALESCE(?,reported_firmware),last_seen_at=?,updated_at=? WHERE id=?', [$level, $input['device_status'], $version, $time, $time, $device['id']]);
        query('INSERT INTO sensor_logs(device_id,water_level,alarm_status,created_at) VALUES(?,?,?,?)', [$identifier, $level, classify($level, $ranges), $time]);
        $response = deviceConfig($device);
        db()->commit();
        return $response;
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
}
function startSession(): void
{
    if (session_status() === PHP_SESSION_NONE) session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax', 'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
}
function csrf(): string
{
    startSession();
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}
function checkCsrf(): void
{
    $provided = $_POST['csrf'] ?? '';
    if (!is_string($provided) || !hash_equals(csrf(), $provided)) fail('The form has expired. Reload the page.');
}
function jsonResponse(array $body, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    exit;
}
function api(callable $handler, string $method): never
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        header('Allow: ' . $method);
        jsonResponse(['status' => 'error', 'message' => 'Method not allowed.'], 405);
    }
    try {
        jsonResponse($handler());
    } catch (InvalidArgumentException $e) {
        jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 422);
    } catch (OutOfBoundsException $e) {
        jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 404);
    }
}
