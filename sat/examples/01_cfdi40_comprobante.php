<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/class.XMLS.php';
require_once __DIR__ . '/../classes/cfdv40/Comprobante.php';
require_once __DIR__ . '/../classes/cfdv40/ComprobanteEmisor.php';
require_once __DIR__ . '/../classes/cfdv40/ComprobanteReceptor.php';
require_once __DIR__ . '/../classes/cfdv40/ComprobanteConceptos.php';
require_once __DIR__ . '/../classes/cfdv40/ComprobanteConceptosConcepto.php';
require_once __DIR__ . '/../classes/cfdv40/ComprobanteConceptosConceptoImpuestos.php';
require_once __DIR__ . '/../classes/cfdv40/ComprobanteConceptosConceptoImpuestosTraslados.php';
require_once __DIR__ . '/../classes/cfdv40/ComprobanteConceptosConceptoImpuestosTrasladosTraslado.php';
require_once __DIR__ . '/../classes/cfdv40/ComprobanteImpuestos.php';
require_once __DIR__ . '/../classes/cfdv40/ComprobanteImpuestosTraslados.php';
require_once __DIR__ . '/../classes/cfdv40/ComprobanteImpuestosTrasladosTraslado.php';

use SAT\Generated\cfdv40\Comprobante;
use SAT\Generated\cfdv40\ComprobanteConceptos;
use SAT\Generated\cfdv40\ComprobanteConceptosConcepto;
use SAT\Generated\cfdv40\ComprobanteConceptosConceptoImpuestos;
use SAT\Generated\cfdv40\ComprobanteConceptosConceptoImpuestosTraslados;
use SAT\Generated\cfdv40\ComprobanteConceptosConceptoImpuestosTrasladosTraslado;
use SAT\Generated\cfdv40\ComprobanteEmisor;
use SAT\Generated\cfdv40\ComprobanteImpuestos;
use SAT\Generated\cfdv40\ComprobanteImpuestosTraslados;
use SAT\Generated\cfdv40\ComprobanteImpuestosTrasladosTraslado;
use SAT\Generated\cfdv40\ComprobanteReceptor;

$cfdi = new Comprobante([
    'Version' => '4.0',
    'Serie' => 'A',
    'Folio' => '1001',
    'Fecha' => date('Y-m-d\\TH:i:s'),
    'SubTotal' => '1000.00',
    'Moneda' => 'MXN',
    'Total' => '1160.00',
    'TipoDeComprobante' => 'I',
    'Exportacion' => '01',
    'LugarExpedicion' => '64000',
]);

$cfdi->Emisor = new ComprobanteEmisor([
    'Rfc' => 'AAA010101AAA',
    'Nombre' => 'EMPRESA DEMO SA DE CV',
    'RegimenFiscal' => '601',
]);

$cfdi->Receptor = new ComprobanteReceptor([
    'Rfc' => 'XAXX010101000',
    'Nombre' => 'PUBLICO EN GENERAL',
    'DomicilioFiscalReceptor' => '64000',
    'RegimenFiscalReceptor' => '616',
    'UsoCFDI' => 'S01',
]);

$cfdi->Conceptos = new ComprobanteConceptos();
$concepto = new ComprobanteConceptosConcepto([
    'ClaveProdServ' => '01010101',
    'Cantidad' => '1',
    'ClaveUnidad' => 'ACT',
    'Unidad' => 'Actividad',
    'Descripcion' => 'Servicio de prueba',
    'ValorUnitario' => '1000.00',
    'Importe' => '1000.00',
    'ObjetoImp' => '02',
]);
$concepto->Impuestos = new ComprobanteConceptosConceptoImpuestos();
$concepto->Impuestos->Traslados = new ComprobanteConceptosConceptoImpuestosTraslados();
$concepto->Impuestos->Traslados->Traslado[] = new ComprobanteConceptosConceptoImpuestosTrasladosTraslado([
    'Base' => '1000.00',
    'Impuesto' => '002',
    'TipoFactor' => 'Tasa',
    'TasaOCuota' => '0.160000',
    'Importe' => '160.00',
]);

$cfdi->Conceptos->Concepto[] = $concepto;
$cfdi->Impuestos = new ComprobanteImpuestos([
    'TotalImpuestosTrasladados' => '160.00',
]);
$cfdi->Impuestos->Traslados = new ComprobanteImpuestosTraslados();
$cfdi->Impuestos->Traslados->Traslado[] = new ComprobanteImpuestosTrasladosTraslado([
    'Base' => '1000.00',
    'Impuesto' => '002',
    'TipoFactor' => 'Tasa',
    'TasaOCuota' => '0.160000',
    'Importe' => '160.00',
]);

header('Content-Type: application/xml; charset=UTF-8');
echo (string)$cfdi;
