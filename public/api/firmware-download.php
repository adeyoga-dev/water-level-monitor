<?php
require dirname(__DIR__, 2) . '/app/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET') { header('Allow: GET'); jsonResponse(['status'=>'error','message'=>'Use GET.'],405); }
$id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
if (!$id) jsonResponse(['status'=>'error','message'=>'Invalid ID.'],422);
$firmware = query('SELECT * FROM firmwares WHERE id=?', [$id])->fetch();
if (!$firmware) jsonResponse(['status'=>'error','message'=>'Firmware not found.'],404);
$root = realpath(dirname(__DIR__,2) . '/storage/firmware');
$path = realpath(dirname(__DIR__,2) . '/' . $firmware['file_path']);
if (!$root || !$path || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) jsonResponse(['status'=>'error','message'=>'File not found.'],404);
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $firmware['version'] . '.bin"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
readfile($path);
