<?php
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/admin.php';
require dirname(__DIR__) . '/app/layout.php';
$devices = deviceList();
pageStart('Devices', 'devices');
// pageTitle('DEVICE MANAGEMENT', 'Devices', 'Manage device identities, reporting intervals, and alarm settings.');
?>
<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
        <h2 class="h5 mb-0 mr-3">Device list <span class="badge badge-secondary"><?= count($devices) ?></span></h2>
        <a class="btn btn-primary my-1" href="/device-edit.php">Add device</a>
    </div>
    <?php deviceTable($devices) ?>
</div>
<?php pageEnd(); ?>