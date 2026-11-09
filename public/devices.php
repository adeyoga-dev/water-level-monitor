<?php
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/admin.php';
require dirname(__DIR__) . '/app/layout.php';
$devices = deviceList();
pageStart('Perangkat', 'devices');
// pageTitle('PENGELOLAAN PERANGKAT', 'Perangkat', 'Kelola identitas perangkat, interval pelaporan, dan pengaturan alarm.');
?>
<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
        <h2 class="h5 mb-0 mr-3">Daftar perangkat <span class="badge badge-secondary"><?= count($devices) ?></span></h2>
        <a class="btn btn-primary my-1" href="/device-edit.php">Tambah perangkat</a>
    </div>
    <?php deviceTable($devices) ?>
</div>
<?php pageEnd(); ?>