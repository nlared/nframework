<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from CLI.\n");
    exit(1);
}

if ($argc < 3) {
    fwrite(STDERR, "Usage: php nframework/xsd2xmls.php <schema.xsd> <output_dir> [namespace] [--overwrite]\n");
    exit(1);
}

$xsdPath = $argv[1];
$outputDir = $argv[2];
$namespace = $argv[3] ?? 'Generated\\XML';
$overwrite = in_array('--overwrite', $argv, true);

require_once __DIR__ . '/../includes/class.XMLS.php';

try {
    $generated = XMLS::generateClassesFromXsd(
        $xsdPath,
        $outputDir,
        $namespace,
        ['overwrite' => $overwrite]
    );

    echo "Generated " . count($generated) . " class files\n";
    foreach ($generated as $filePath) {
        echo $filePath . "\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
