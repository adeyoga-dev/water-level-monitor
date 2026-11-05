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
<?php if ($error): ?><div class="notice error"><?= h($error) ?></div><?php endif ?>
<div class="form-grid firmware-grid">
    <div class="panel form-panel">
        <h2>Upload firmware</h2>
        <p class="hint">Latest global version: <strong><?= h($latest['version'] ?? 'Not available') ?></strong></p>
        <form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><label>Firmware version<input name="version" required maxlength="64" placeholder="v1.0.0" value="<?= h($_POST['version'] ?? '') ?>"></label><label>Firmware file (.bin)<input class="file-input" type="file" name="firmware" accept=".bin" required></label>
            <p class="hint">Maximum 16 MB. Use an application binary for the same board and OTA partition layout on every device.</p><label>Release notes<textarea name="description" rows="5" maxlength="10000" placeholder="What changed in this release?"><?= h($_POST['description'] ?? '') ?></textarea></label><button class="button" type="submit">↑ Upload & publish</button>
        </form>
    </div>
    <div class="panel">
        <div class="panel-heading">
            <h2>Firmware history</h2><span class="count-pill"><?= $count ?> versions</span>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>VERSION</th>
                        <th>RELEASE NOTES</th>
                        <th>FILE</th>
                    </tr>
                </thead>
                <tbody><?php foreach ($firmwares as $firmware): ?><tr>
                            <td><strong><?= h($firmware['version']) ?></strong><?php if ($latest && $firmware['id'] === $latest['id']): ?><small class="text-link">Global target</small><?php endif ?><small><?= h(displayTime($firmware['created_at'])) ?></small></td>
                            <td class="changelog"><?= nl2br(h($firmware['description'])) ?></td>
                            <td><?= h(number_format((float)$firmware['file_size'] / 1024, 1)) ?> KB<small><a class="text-link" href="/api/firmware-download.php?id=<?= h($firmware['id']) ?>">Download .bin ↓</a></small></td>
                        </tr><?php endforeach ?><?php if (!$firmwares): ?><tr>
                            <td colspan="3" class="empty">No firmware has been uploaded yet.</td>
                        </tr><?php endif ?></tbody>
            </table>
        </div>
        <div class="pagination-row"><a href="?page=<?= max(1, $page - 1) ?>">← Previous</a><span><?= $page ?> / <?= $pages ?></span><a href="?page=<?= min($pages, $page + 1) ?>">Next →</a></div>
    </div>
</div>
<?php pageEnd(); ?>