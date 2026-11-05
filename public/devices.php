<?php
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/admin.php';
require dirname(__DIR__) . '/app/layout.php';
$devices = deviceList();
pageStart('Devices', 'devices');
pageTitle('DEVICE MANAGEMENT', 'Devices', 'Manage device identities, reporting intervals, and alarm settings.');
?>
<div class="panel">
    <div class="panel-heading">
        <h2>Device list <span class="count-pill"><?= count($devices) ?></span></h2><a class="button" href="/device-edit.php">+ Add device</a>
    </div><?php deviceTable($devices) ?>
</div>
<?php pageEnd(); ?>