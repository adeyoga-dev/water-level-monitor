<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require dirname(__DIR__) . '/app/bootstrap.php';
$tables = ['users_2fa', 'users_audit_log', 'users_confirmations', 'users_otps', 'users_remembered', 'users_resets', 'users_throttling', 'users'];
echo 'Database: ' . config('DB_DATABASE') . PHP_EOL;
echo 'Authentication tables to remove: ' . implode(', ', $tables) . PHP_EOL;
if (!in_array('--apply', $argv, true)) exit("Preview only. After approval, run with --apply. Account data will be permanently removed.\n");
// Refuse unknown dependencies rather than disabling foreign-key checks.
$references = query('SELECT TABLE_NAME, REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL')->fetchAll();
foreach ($references as $reference) {
    if (in_array($reference['REFERENCED_TABLE_NAME'], $tables, true)) throw new RuntimeException('A foreign key references an authentication table. Review dependencies before cleanup.');
}
foreach ($tables as $table) db()->exec('DROP TABLE IF EXISTS `' . $table . '`');
echo "Authentication tables removed. Business tables were not targeted.\n";
