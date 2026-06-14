<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/class.XMLS.php';
require_once __DIR__ . '/../classes/cfdv40/Comprobante.php';
require_once __DIR__ . '/../classes/cfdv40/ComprobanteComplemento.php';
require_once __DIR__ . '/../classes/cfdv40/ComprobanteEmisor.php';
require_once __DIR__ . '/../classes/cfdv40/ComprobanteReceptor.php';
require_once __DIR__ . '/../classes/cfdv40/ComprobanteConceptos.php';
require_once __DIR__ . '/../classes/cfdv40/ComprobanteConceptosConcepto.php';
require_once __DIR__ . '/../classes/nomina12/Nomina.php';
require_once __DIR__ . '/../classes/nomina12/NominaPercepciones.php';
require_once __DIR__ . '/../classes/nomina12/NominaPercepcionesPercepcion.php';
require_once __DIR__ . '/../classes/nomina12/NominaDeducciones.php';
require_once __DIR__ . '/../classes/nomina12/NominaDeduccionesDeduccion.php';

use SAT\Generated\cfdv40\Comprobante;

$xmlPath = $argv[1] ?? '';

$sampleXml = <<<XML
<Comprobante Version="4.0" Serie="A" Folio="1001" Fecha="2026-06-13T12:00:00" SubTotal="1000.00" Moneda="MXN" Total="1160.00" TipoDeComprobante="I" Exportacion="01" LugarExpedicion="64000">
  <Emisor Rfc="AAA010101AAA" Nombre="EMPRESA DEMO SA DE CV" RegimenFiscal="601"/>
  <Receptor Rfc="XAXX010101000" Nombre="PUBLICO EN GENERAL" DomicilioFiscalReceptor="64000" RegimenFiscalReceptor="616" UsoCFDI="S01"/>
  <Conceptos>
    <Concepto ClaveProdServ="01010101" Cantidad="1" ClaveUnidad="ACT" Unidad="Actividad" Descripcion="Servicio de prueba" ValorUnitario="1000.00" Importe="1000.00" ObjetoImp="01"/>
  </Conceptos>
</Comprobante>
XML;

$dom = new DOMDocument();
$dom->preserveWhiteSpace = false;

if ($xmlPath !== '') {
    if (!is_file($xmlPath)) {
        fwrite(STDERR, "XML file not found: {$xmlPath}\n");
        exit(1);
    }

    if (!$dom->load($xmlPath)) {
        fwrite(STDERR, "Cannot parse XML file: {$xmlPath}\n");
        exit(1);
    }
} else {
    if (!$dom->loadXML($sampleXml)) {
        fwrite(STDERR, "Cannot parse embedded sample XML\n");
        exit(1);
    }
}

$classTranslations = [
    '\\Complemento' => '\\SAT\\Generated\\cfdv40\\ComprobanteComplemento',
    '\\Emisor' => '\\SAT\\Generated\\cfdv40\\ComprobanteEmisor',
    '\\Receptor' => '\\SAT\\Generated\\cfdv40\\ComprobanteReceptor',
    '\\Conceptos' => '\\SAT\\Generated\\cfdv40\\ComprobanteConceptos',
    '\\Concepto' => '\\SAT\\Generated\\cfdv40\\ComprobanteConceptosConcepto',
    '\\Percepciones' => '\\SAT\\Generated\\nomina12\\NominaPercepciones',
    '\\Percepcion' => '\\SAT\\Generated\\nomina12\\NominaPercepcionesPercepcion',
    '\\Deducciones' => '\\SAT\\Generated\\nomina12\\NominaDeducciones',
    '\\Deduccion' => '\\SAT\\Generated\\nomina12\\NominaDeduccionesDeduccion',
];

$namespaceTranslations = [
    '\\nomina12' => '\\SAT\\Generated\\nomina12',
];

$cfdi = new Comprobante();
$cfdi->deserialize($dom->documentElement, $namespaceTranslations, $classTranslations);

$conceptCount = 0;
if (isset($cfdi->Conceptos) && is_object($cfdi->Conceptos) && isset($cfdi->Conceptos->Concepto) && is_array($cfdi->Conceptos->Concepto)) {
    $conceptCount = count($cfdi->Conceptos->Concepto);
}

echo "Lectura OK\n";
echo 'Serie: ' . ($cfdi->Serie ?? '') . "\n";
echo 'Folio: ' . ($cfdi->Folio ?? '') . "\n";
echo 'Total: ' . ($cfdi->Total ?? '') . "\n";
echo 'Emisor RFC: ' . ($cfdi->Emisor->Rfc ?? '') . "\n";
echo 'Receptor RFC: ' . ($cfdi->Receptor->Rfc ?? '') . "\n";
echo 'Conceptos: ' . $conceptCount . "\n\n";

echo "XML reconstruido:\n";
echo (string)$cfdi . "\n";
