<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/core.php';
require dirname(__DIR__, 2) . '/app/auth.php';
putenv('ADMIN_EMAIL=admin@example.test');
putenv('ADMIN_PASSWORD=TestPassword123!');
$_SERVER['REMOTE_ADDR'] = 'fixture-' . bin2hex(random_bytes(16));
$file = dirname(__DIR__, 2) . '/storage/auth/' . hash('sha256', $_SERVER['REMOTE_ADDR']) . '.json';
$checks = 0;
function checkAuth(bool $condition): void {
    global $checks;
    if (!$condition) throw new RuntimeException('Authentication regression failed.');
    $checks++;
}
try {
    startSession();
    checkAuth(currentUser() === null);
    $_SESSION['signed_in_at'] = time();
    checkAuth(currentUser() === null);
    checkAuth(signInAdmin('ADMIN@example.test', 'TestPassword123!'));
    checkAuth(currentUser()['email'] === 'admin@example.test');
    $_SESSION['flow_admin']['signed_in_at'] = time() - 28800;
    checkAuth(currentUser() === null);
    signInAdmin('admin@example.test', 'TestPassword123!');
    putenv('ADMIN_PASSWORD=ChangedPassword123!');
    checkAuth(currentUser() === null);
    putenv('ADMIN_PASSWORD=');
    try { signInAdmin('admin@example.test', ''); checkAuth(false); } catch (InvalidArgumentException) { checkAuth(true); }
    putenv('ADMIN_PASSWORD=TestPassword123!');
    for ($i = 0; $i < 5; $i++) checkAuth(!signInAdmin('admin@example.test', 'wrong'));
    $_SESSION = [];
    try { signInAdmin('admin@example.test', 'TestPassword123!'); checkAuth(false); } catch (InvalidArgumentException) { checkAuth(http_response_code() === 429); }
    file_put_contents($file, json_encode(array_fill(0, 5, time() - 901)));
    checkAuth(signInAdmin('admin@example.test', 'TestPassword123!'));
    echo "$checks authentication checks passed.\n";
} finally {
    if (is_file($file)) unlink($file);
}
