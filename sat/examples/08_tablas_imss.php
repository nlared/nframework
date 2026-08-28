<?php

declare(strict_types=1);

require_once __DIR__ . '/../tablas.php';

$anio = isset($argv[1]) ? (int) $argv[1] : 2026;
$sueldoBase = isset($argv[2]) ? (float) $argv[2] : 650.00;
$claseRiesgo = isset($argv[3]) ? (int) $argv[3] : 2;

$imss = calculoimss($sueldoBase, $anio, $claseRiesgo);

echo "Ejemplo IMSS" . PHP_EOL;
echo "Anio: {$anio}" . PHP_EOL;
echo "Sueldo base diario: " . number_format($sueldoBase, 2) . PHP_EOL;
echo "Clase de riesgo: {$claseRiesgo}" . PHP_EOL;

echo "\nCuotas" . PHP_EOL;
print_r($imss);

$totalPatron = 0.0;
$totalTrabajador = 0.0;

foreach ($imss as $concepto) {
    $totalPatron += (float) $concepto['patron'];
    $totalTrabajador += (float) $concepto['trabajador'];
}

echo "\nTotales" . PHP_EOL;
echo "Patron: " . number_format($totalPatron, 2) . PHP_EOL;
echo "Trabajador: " . number_format($totalTrabajador, 2) . PHP_EOL;
