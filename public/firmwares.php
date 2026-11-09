<?php
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/admin.php';
require dirname(__DIR__) . '/app/layout.php';
startSession();
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        checkCsrf();
        uploadFirmware($_POST, $_FILES['firmware'] ?? []);
        $_SESSION['flash'] = 'Firmware berhasil diterbitkan untuk semua perangkat.';
        header('Location: /firmwares.php');
        exit;
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    }
}
$page = max(1, (int)($_GET['page'] ?? 1));
$count = (int)query('SELECT COUNT(*) FROM firmwares')->fetchColumn();
$pages = max(1, (int)ceil($count / 20));
$page = min($pages, $page);
$offset = ($page - 1) * 20;
$firmwares = query('SELECT * FROM firmwares ORDER BY id DESC LIMIT 20 OFFSET ' . $offset)->fetchAll();
$latest = latestFirmware();
pageStart('Firmware OTA', 'firmwares');
// pageTitle('PEMBARUAN NIRKABEL', 'Firmware OTA', 'Terbitkan pembaruan firmware untuk semua perangkat ESP32.');
?>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= h(uiText($error)) ?></div><?php endif ?>
<div class="row">
    <div class="col-xl-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h2 class="h5 mb-0">Unggah firmware</h2>
            </div>
            <div class="card-body">
                <p class="small text-muted">Versi global terbaru: <strong><?= h($latest['version'] ?? 'Belum tersedia') ?></strong></p>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                    <div class="form-group">
                        <label for="firmware-version">Versi firmware</label>
                        <input class="form-control" id="firmware-version" name="version" required maxlength="64" placeholder="v1.0.0" value="<?= h($_POST['version'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="firmware-file">Berkas firmware (.bin)</label>
                        <input class="form-control-file" id="firmware-file" type="file" name="firmware" accept=".bin" required aria-describedby="firmware-help">
                        <small class="form-text text-muted" id="firmware-help">Maksimal 16 MB. Gunakan berkas biner aplikasi untuk papan dan susunan partisi OTA yang sama pada setiap perangkat.</small>
                    </div>
                    <div class="form-group">
                        <label for="release-notes">Catatan rilis</label>
                        <textarea class="form-control" id="release-notes" name="description" rows="5" maxlength="10000" placeholder="Apa yang berubah pada rilis ini?"><?= h($_POST['description'] ?? '') ?></textarea>
                    </div>
                    <button class="btn btn-primary" type="submit">Unggah dan terbitkan</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-xl-8 mb-4">
        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
                <h2 class="h5 mb-0 mr-2">Riwayat firmware</h2>
                <span class="badge badge-secondary"><?= $count ?> versi</span>
            </div>
            <div class="table-responsive" tabindex="0" role="region" aria-label="Riwayat firmware">
                <table class="table table-striped mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th scope="col">Versi</th>
                            <th scope="col">Catatan rilis</th>
                            <th scope="col">Berkas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($firmwares as $firmware): ?>
                            <tr>
                                <td class="text-nowrap">
                                    <strong><?= h($firmware['version']) ?></strong>
                                    <?php if ($latest && $firmware['id'] === $latest['id']): ?><small class="d-block text-primary">Target global</small><?php endif ?>
                                    <small class="d-block text-muted"><?= h(uiText(displayTime($firmware['created_at']))) ?></small>
                                </td>
                                <td class="text-break"><?= nl2br(h($firmware['description'])) ?></td>
                                <td class="text-nowrap"><?= h(number_format((float)$firmware['file_size'] / 1024, 1)) ?> KB<small class="d-block"><a href="/api/firmware-download.php?id=<?= h($firmware['id']) ?>">Unduh .bin</a></small></td>
                            </tr>
                        <?php endforeach ?>
                        <?php if (!$firmwares): ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted py-5">Belum ada firmware yang diunggah.</td>
                            </tr>
                        <?php endif ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer d-flex flex-wrap justify-content-between align-items-center">
                <span class="small text-muted mr-2"><?= $page ?> / <?= $pages ?></span>
                <nav aria-label="Halaman firmware">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>">
                            <?php if ($page <= 1): ?><span class="page-link" aria-disabled="true">Sebelumnya</span><?php else: ?><a class="page-link" href="?page=<?= $page - 1 ?>">Sebelumnya</a><?php endif ?>
                        </li>
                        <li class="page-item<?= $page >= $pages ? ' disabled' : '' ?>">
                            <?php if ($page >= $pages): ?><span class="page-link" aria-disabled="true">Berikutnya</span><?php else: ?><a class="page-link" href="?page=<?= $page + 1 ?>">Berikutnya</a><?php endif ?>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>
<?php pageEnd(); ?>