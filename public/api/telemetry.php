<?php
require dirname(__DIR__, 2) . '/app/bootstrap.php';
api(function (): array {
    if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
        jsonResponse(['status' => 'error', 'message' => 'Use Content-Type application/json.'], 415);
    }
    $raw = file_get_contents('php://input', false, null, 0, 16385);
    if (strlen($raw) > 16384) jsonResponse(['status' => 'error', 'message' => 'Payload is too large.'], 413);
    try {
        $input = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        fail('Invalid JSON.');
    }
    if (!is_array($input) || array_is_list($input)) fail('The body must be a JSON object.');
    return telemetry($input);
}, 'POST');
