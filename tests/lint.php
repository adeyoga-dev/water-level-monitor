<?php
$root = dirname(__DIR__);
$failed = false;
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file->getExtension() !== 'php') continue;
    passthru('"' . PHP_BINARY . '" -l ' . escapeshellarg($file->getPathname()),$code);
    if ($code !== 0) $failed = true;
}
exit($failed ? 1 : 0);
