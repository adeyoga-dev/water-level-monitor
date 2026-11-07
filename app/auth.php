<?php
declare(strict_types=1);

function adminCredentials(): array
{
    return [strtolower(trim(config('ADMIN_EMAIL'))), config('ADMIN_PASSWORD')];
}
function adminFingerprint(): string
{
    return hash('sha256', json_encode(adminCredentials(), JSON_THROW_ON_ERROR));
}
function currentUser(): ?array
{
    startSession();
    [$email, $password] = adminCredentials();
    $session = $_SESSION['flow_admin'] ?? null;
    if (!$email || !$password || !is_array($session) || time() - ($session['signed_in_at'] ?? 0) >= 28800 || !hash_equals(adminFingerprint(), $session['fingerprint'] ?? '')) {
        unset($_SESSION['flow_admin']);
        return null;
    }
    return ['email' => $email];
}
function requireAccess(): array
{
    $user = currentUser();
    if (!$user) {
        if (str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/')) jsonResponse(['status' => 'error', 'message' => 'Please sign in again.'], 401);
        header('Location: /login.php');
        exit;
    }
    header('Cache-Control: no-store');
    return $user;
}
function signInAdmin(string $email, string $password): bool
{
    [$expectedEmail, $expectedPassword] = adminCredentials();
    if (!filter_var($expectedEmail, FILTER_VALIDATE_EMAIL) || $expectedPassword === '') throw new InvalidArgumentException('Administrator login is not configured.');
    $directory = dirname(__DIR__) . '/storage/auth';
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) throw new RuntimeException('Cannot initialize login protection.');
    $file = fopen($directory . '/' . hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown') . '.json', 'c+');
    if (!$file) throw new RuntimeException('Cannot open login protection.');
    try {
        if (!flock($file, LOCK_EX)) throw new RuntimeException('Cannot lock login protection.');
        $attempts = json_decode(stream_get_contents($file), true) ?: [];
        $attempts = array_values(array_filter($attempts, fn($timestamp) => is_int($timestamp) && $timestamp > time() - 900));
        if (count($attempts) >= 5) {
            http_response_code(429);
            throw new InvalidArgumentException('Too many attempts. Please try again in 15 minutes.');
        }
        $valid = hash_equals($expectedEmail, strtolower(trim($email))) & hash_equals(hash('sha256', $expectedPassword), hash('sha256', $password));
        if ($valid) $attempts = []; else $attempts[] = time();
        rewind($file);
        if (!ftruncate($file, 0) || fwrite($file, json_encode($attempts, JSON_THROW_ON_ERROR)) === false || !fflush($file)) throw new RuntimeException('Cannot save login protection.');
    } finally {
        flock($file, LOCK_UN);
        fclose($file);
    }
    if (!$valid) return false;
    startSession();
    session_regenerate_id(true);
    $_SESSION = ['flow_admin' => ['signed_in_at' => time(), 'fingerprint' => adminFingerprint()]];
    return true;
}
