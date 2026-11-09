<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require dirname(__DIR__) . '/app/bootstrap.php';
echo 'Database: ' . config('DB_DATABASE') . PHP_EOL;
if (!in_array('--apply', $argv, true)) exit("Preview: add nullable devices.station_name VARCHAR(150). Existing data is preserved. Run with --apply after approval.\n");
if (query("SHOW COLUMNS FROM devices LIKE 'station_name'")->fetch()) exit("station_name already exists. No changes applied.\n");
db()->exec(file_get_contents(dirname(__DIR__) . '/database/migrations/004_station_name.sql'));
echo "station_name added. Existing station names are empty; set them in Devices.\n";
