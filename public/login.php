<?php
require dirname(__DIR__) . '/app/bootstrap.php';
header('Cache-Control: no-store');
$error = null;
if (currentUser()) {
    header('Location: /');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        checkCsrf();
        if (!is_string($_POST['email'] ?? null) || !is_string($_POST['password'] ?? null)) fail('Enter an email and password.');
        if (!signInAdmin($_POST['email'], $_POST['password'])) fail('Invalid email or password.');
        header('Location: /');
        exit;
    } catch (InvalidArgumentException $e) {
        if (http_response_code() !== 429) http_response_code(422);
        $error = $e->getMessage();
    }
}
$title = 'Sign in';
require dirname(__DIR__) . '/app/auth-view.php';
