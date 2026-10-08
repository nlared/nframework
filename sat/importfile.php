<?php
// Uso:
//   CLI:      php importfile.php <archivo.xml|archivo.zip>
//   Include:  definir $file y hacer require de este archivo (puede repetirse).
// Las funciones viven en importlib.php para poder incluirlo varias veces sin redeclararlas.
$isDirectCli = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg')
    && isset($_SERVER['SCRIPT_FILENAME'])
    && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__;

if ($isDirectCli) {
    require 'include.php';
    if (isset($argv[1])) {
        $file = $argv[1];
    } else {
        fwrite(STDERR, "Usage: php importfile.php <file>\n");
        exit(1);
    }
}

require_once __DIR__ . '/importlib.php';

try {
    $importResult = satImportFile((string) ($file ?? ''));
    echo "Importados: " . $importResult['imported'] . ", errores: " . count($importResult['errors']) . "\n";
} catch (Throwable $e) {
    echo "An error occurred: " . $e->getMessage() . "\n";
    if ($isDirectCli) {
        fwrite(STDERR, "An error occurred: " . $e->getMessage() . "\n");
        exit(1);
    }
}
