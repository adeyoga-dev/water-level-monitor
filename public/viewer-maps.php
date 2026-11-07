<?php
declare(strict_types=1);
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') { http_response_code(405); header('Allow: GET'); exit; }
define('FLOW_VIEWER', true);
require __DIR__ . '/maps.php';
