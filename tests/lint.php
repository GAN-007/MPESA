<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$directories = [$root . '/src', $root . '/tests'];
$files = [$root . '/Mpesa.php', $root . '/TransactionCallbacks.php', $root . '/MpesaServiceProvider.php'];
foreach ($directories as $directory) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
}
sort($files);
foreach ($files as $file) {
    $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file);
    passthru($command, $code);
    if ($code !== 0) {
        exit($code);
    }
}
echo "Linted " . count($files) . " PHP files successfully.\n";
