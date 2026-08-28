<?php

declare(strict_types=1);

require_once __DIR__ . '/../tablas.php';

$fecha = $argv[1] ?? '2026-06-23';

echo "Ejemplo vigencias por rubro" . PHP_EOL;
echo "Fecha base: {$fecha}" . PHP_EOL;

$rubros = ['isr', 'subisr', 'riesgo', 'uma', 'salarios'];

foreach ($rubros as $rubro) {
    $anioVigente = anoVigenteRubro($rubro, $fecha);
    echo strtoupper($rubro) . ": anio vigente {$anioVigente}" . PHP_EOL;
}
