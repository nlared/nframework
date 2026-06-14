<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/class.XMLS.php';

$xsdDir = __DIR__ . '/xsd';
$classesDir = __DIR__ . '/classes';

if (!is_dir($xsdDir)) {
    fwrite(STDERR, "XSD directory not found: {$xsdDir}\n");
    exit(1);
}

if (!is_dir($classesDir) && !mkdir($classesDir, 0775, true) && !is_dir($classesDir)) {
    fwrite(STDERR, "Cannot create classes directory: {$classesDir}\n");
    exit(1);
}

$xsdFiles = glob($xsdDir . '/*.xsd') ?: [];
sort($xsdFiles);

if (empty($xsdFiles)) {
    fwrite(STDERR, "No XSD files found in {$xsdDir}\n");
    exit(1);
}

$totalGenerated = 0;
$processed = 0;
$withErrors = 0;

foreach ($xsdFiles as $xsdPath) {
    $baseName = pathinfo($xsdPath, PATHINFO_FILENAME);
    $schemaOutDir = $classesDir . '/' . $baseName;
    $namespace = 'SAT\\Generated\\' . preg_replace('/[^a-zA-Z0-9_]/', '', $baseName);

    if (!is_dir($schemaOutDir) && !mkdir($schemaOutDir, 0775, true) && !is_dir($schemaOutDir)) {
        fwrite(STDERR, "Cannot create output directory: {$schemaOutDir}\n");
        $withErrors++;
        continue;
    }

    try {
        $generated = XMLS::generateClassesFromXsd(
            $xsdPath,
            $schemaOutDir,
            $namespace,
            [
                'overwrite' => true,
                'recursiveElements' => true,
                'maxDepth' => 16,
            ]
        );

        $count = count($generated);
        $totalGenerated += $count;
        $processed++;

        echo sprintf("[%s] generated %d classes\n", basename($xsdPath), $count);
    } catch (Throwable $e) {
        $withErrors++;
        fwrite(STDERR, sprintf("[%s] error: %s\n", basename($xsdPath), $e->getMessage()));
    }
}

echo sprintf(
    "Done. Processed: %d, Errors: %d, Total classes generated: %d\n",
    $processed,
    $withErrors,
    $totalGenerated
);
