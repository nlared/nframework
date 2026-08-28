<?php

declare(strict_types=1);

require_once __DIR__ . '/../tablas.php';

$anio = isset($argv[1]) ? (int) $argv[1] : 2026;
$ingresoMensual = isset($argv[2]) ? (float) $argv[2] : 18500.00;

$isr = buscarisrentablas($anio, $ingresoMensual, 1, 1.0);
$subsidio = buscarsubisrentablas($anio, $ingresoMensual);

if (empty($isr)) {
    fwrite(STDERR, "No se encontro tabla ISR para el anio {$anio}" . PHP_EOL);
    exit(1);
}

echo "Ejemplo ISR/Subsidio" . PHP_EOL;
echo "Anio: {$anio}" . PHP_EOL;
echo "Ingreso mensual: " . number_format($ingresoMensual, 2) . PHP_EOL;
echo "ISR calculado: " . number_format((float) $isr['isr'], 2) . PHP_EOL;
echo "Tasa efectiva: " . number_format((float) $isr['tasaefectiva'], 4) . "%" . PHP_EOL;

echo "\nDetalle ISR" . PHP_EOL;
print_r($isr);

echo "\nDetalle Subsidio" . PHP_EOL;
print_r($subsidio);
