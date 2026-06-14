<?php

declare(strict_types=1);

$url = 'https://github.com/phpcfdi/resources-sat-xml/archive/master.zip';
$targetDir = __DIR__ . '/xsd';
$tmpZip = sys_get_temp_dir() . '/resources-sat-xml.zip';
$tmpExtract = sys_get_temp_dir() . '/resources-sat-xml-' . uniqid('', true);

if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
    fwrite(STDERR, "Cannot create xsd directory: {$targetDir}\n");
    exit(1);
}

echo "Downloading SAT XML resources...\n";
$downloaded = @file_get_contents($url);
if ($downloaded === false) {
    fwrite(STDERR, "Download failed from {$url}\n");
    exit(1);
}

if (file_put_contents($tmpZip, $downloaded) === false) {
    fwrite(STDERR, "Cannot write temp zip file: {$tmpZip}\n");
    exit(1);
}

if (!mkdir($tmpExtract, 0775, true) && !is_dir($tmpExtract)) {
    @unlink($tmpZip);
    fwrite(STDERR, "Cannot create temp extraction dir: {$tmpExtract}\n");
    exit(1);
}

$extracted = false;

if (class_exists('ZipArchive')) {
    $zip = new ZipArchive();
    if ($zip->open($tmpZip) === true) {
        $extracted = $zip->extractTo($tmpExtract);
        $zip->close();
    }
}

if (!$extracted) {
    $unzipBin = trim((string)shell_exec('command -v unzip'));
    if ($unzipBin !== '') {
        $cmd = escapeshellcmd($unzipBin)
            . ' -oq '
            . escapeshellarg($tmpZip)
            . ' -d '
            . escapeshellarg($tmpExtract)
            . ' 2>&1';
        exec($cmd, $out, $code);
        $extracted = ($code === 0);
    }
}

if (!$extracted) {
    @unlink($tmpZip);
    fwrite(STDERR, "Cannot extract zip file. Enable php-zip extension or install unzip binary.\n");
    exit(1);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($tmpExtract, FilesystemIterator::SKIP_DOTS)
);

$saved = 0;
foreach ($iterator as $fileInfo) {
    if (!($fileInfo instanceof SplFileInfo)) {
        continue;
    }

    if (strtolower($fileInfo->getExtension()) !== 'xsd') {
        continue;
    }

    $destination = $targetDir . '/' . $fileInfo->getBasename();
    if (copy($fileInfo->getPathname(), $destination)) {
        $saved++;
    }
}

@unlink($tmpZip);

$cleanupIterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($tmpExtract, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);
foreach ($cleanupIterator as $entry) {
    if ($entry->isDir()) {
        @rmdir($entry->getPathname());
    } else {
        @unlink($entry->getPathname());
    }
}
@rmdir($tmpExtract);

echo "Done. Saved {$saved} XSD files into {$targetDir}\n";
