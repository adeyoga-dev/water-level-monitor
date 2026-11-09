<?php
require dirname(__DIR__) . '/app/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Gunakan metode POST.');
}
try {
    checkCsrf();
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    exit('Formulir tidak valid.');
}
$_SESSION = [];
session_regenerate_id(true);
header('Location: /login.php');
