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
        $_SESSION['flash'] = 'Firmware published successfully for all devices.';
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
pageStart('OTA firmware', 'firmwares');
pageTitle('OVER THE AIR', 'OTA firmware', 'Publish firmware updates for all ESP32 devices.');
?>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= h($error) ?></div><?php endif ?>
<div class="row">
    <div class="col-xl-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h2 class="h5 mb-0">Upload firmware</h2>
            </div>
            <div class="card-body">
                <p class="small text-muted">Latest global version: <strong><?= h($latest['version'] ?? 'Not available') ?></strong></p>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                    <div class="form-group">
                        <label for="firmware-version">Firmware version</label>
                        <input class="form-control" id="firmware-version" name="version" required maxlength="64" placeholder="v1.0.0" value="<?= h($_POST['version'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="firmware-file">Firmware file (.bin)</label>
                        <input class="form-control-file" id="firmware-file" type="file" name="firmware" accept=".bin" required aria-describedby="firmware-help">
                        <small class="form-text text-muted" id="firmware-help">Maximum 16 MB. Use an application binary for the same board and OTA partition layout on every device.</small>
                    </div>
                    <div class="form-group">
                        <label for="release-notes">Release notes</label>
                        <textarea class="form-control" id="release-notes" name="description" rows="5" maxlength="10000" placeholder="What changed in this release?"><?= h($_POST['description'] ?? '') ?></textarea>
                    </div>
                    <button class="btn btn-primary" type="submit">Upload &amp; publish</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-xl-8 mb-4">
        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
                <h2 class="h5 mb-0 mr-2">Firmware history</h2>
                <span class="badge badge-secondary"><?= $count ?> versions</span>
            </div>
            <div class="table-responsive" tabindex="0" role="region" aria-label="Firmware history">
                <table class="table table-striped mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th scope="col">Version</th>
                            <th scope="col">Release notes</th>
                            <th scope="col">File</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($firmwares as $firmware): ?>
                            <tr>
                                <td class="text-nowrap">
                                    <strong><?= h($firmware['version']) ?></strong>
                                    <?php if ($latest && $firmware['id'] === $latest['id']): ?><small class="d-block text-primary">Global target</small><?php endif ?>
                                    <small class="d-block text-muted"><?= h(displayTime($firmware['created_at'])) ?></small>
                                </td>
                                <td class="text-break"><?= nl2br(h($firmware['description'])) ?></td>
                                <td class="text-nowrap"><?= h(number_format((float)$firmware['file_size'] / 1024, 1)) ?> KB<small class="d-block"><a href="/api/firmware-download.php?id=<?= h($firmware['id']) ?>">Download .bin</a></small></td>
                            </tr>
                        <?php endforeach ?>
                        <?php if (!$firmwares): ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted py-5">No firmware has been uploaded yet.</td>
                            </tr>
                        <?php endif ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer d-flex flex-wrap justify-content-between align-items-center">
                <span class="small text-muted mr-2"><?= $page ?> / <?= $pages ?></span>
                <nav aria-label="Firmware pages">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>">
                            <?php if ($page <= 1): ?><span class="page-link" aria-disabled="true">Previous</span><?php else: ?><a class="page-link" href="?page=<?= $page - 1 ?>">Previous</a><?php endif ?>
                        </li>
                        <li class="page-item<?= $page >= $pages ? ' disabled' : '' ?>">
                            <?php if ($page >= $pages): ?><span class="page-link" aria-disabled="true">Next</span><?php else: ?><a class="page-link" href="?page=<?= $page + 1 ?>">Next</a><?php endif ?>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>
<?php pageEnd(); ?>