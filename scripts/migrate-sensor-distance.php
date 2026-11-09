<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require dirname(__DIR__) . '/app/bootstrap.php';

echo 'Database: ' . config('DB_DATABASE') . PHP_EOL;
$apply = in_array('--apply', $argv, true);
foreach (['devices', 'sensor_logs'] as $table) {
    if (query("SHOW COLUMNS FROM `$table` LIKE 'sensor_distance'")->fetch()) {
        echo "$table.sensor_distance already exists.\n";
        continue;
    }
    echo ($apply ? 'Adding: ' : 'Preview: add ') . "$table.sensor_distance DOUBLE NULL.\n";
    if ($apply) db()->exec("ALTER TABLE `$table` ADD COLUMN sensor_distance DOUBLE NULL AFTER water_level");
}
if (!$apply) echo "Run with --apply after approval. Existing readings are not converted.\n";
