<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/class.XMLS.php';
require_once __DIR__ . '/../classes/Pagos20/Pagos.php';
require_once __DIR__ . '/../classes/Pagos20/PagosTotales.php';
require_once __DIR__ . '/../classes/Pagos20/PagosPago.php';
require_once __DIR__ . '/../classes/Pagos20/PagosPagoDoctorelacionado.php';

use SAT\Generated\Pagos20\Pagos;
use SAT\Generated\Pagos20\PagosPago;
use SAT\Generated\Pagos20\PagosPagoDoctorelacionado;
use SAT\Generated\Pagos20\PagosTotales;

$pagos = new Pagos([
    'Version' => '2.0',
]);

$pagos->Totales = new PagosTotales([
    'MontoTotalPagos' => '1160.00',
    'TotalTrasladosBaseIVA16' => '1000.00',
    'TotalTrasladosImpuestoIVA16' => '160.00',
]);

$fechaPago = date('Y-m-d\\TH:i:s');
$pago = new PagosPago([
    'FechaPago' => $fechaPago,
    'FormaDePagoP' => '03',
    'MonedaP' => 'MXN',
    'Monto' => '1160.00',
]);

$pago->DoctoRelacionado[] = new PagosPagoDoctorelacionado([
    'IdDocumento' => 'A57A6B9B-AC31-4D78-A27B-222222222222',
    'Serie' => 'A',
    'Folio' => '1001',
    'MonedaDR' => 'MXN',
    'NumParcialidad' => '1',
    'ImpSaldoAnt' => '1160.00',
    'ImpPagado' => '1160.00',
    'ImpSaldoInsoluto' => '0.00',
    'ObjetoImpDR' => '02',
]);

$pagos->Pago[] = $pago;

header('Content-Type: application/xml; charset=UTF-8');
echo (string)$pagos;
