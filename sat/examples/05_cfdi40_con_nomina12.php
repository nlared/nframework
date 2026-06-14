<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/class.XMLS.php';

require_once __DIR__ . '/../classes/cfdv40/Comprobante.php';
require_once __DIR__ . '/../classes/cfdv40/ComprobanteEmisor.php';
require_once __DIR__ . '/../classes/cfdv40/ComprobanteReceptor.php';
require_once __DIR__ . '/../classes/cfdv40/ComprobanteConceptos.php';
require_once __DIR__ . '/../classes/cfdv40/ComprobanteConceptosConcepto.php';
require_once __DIR__ . '/../classes/cfdv40/ComprobanteComplemento.php';

require_once __DIR__ . '/../classes/nomina12/Nomina.php';
require_once __DIR__ . '/../classes/nomina12/NominaEmisor.php';
require_once __DIR__ . '/../classes/nomina12/NominaReceptor.php';
require_once __DIR__ . '/../classes/nomina12/NominaPercepciones.php';
require_once __DIR__ . '/../classes/nomina12/NominaPercepcionesPercepcion.php';
require_once __DIR__ . '/../classes/nomina12/NominaDeducciones.php';
require_once __DIR__ . '/../classes/nomina12/NominaDeduccionesDeduccion.php';

use SAT\Generated\cfdv40\Comprobante;
use SAT\Generated\cfdv40\ComprobanteComplemento;
use SAT\Generated\cfdv40\ComprobanteConceptos;
use SAT\Generated\cfdv40\ComprobanteConceptosConcepto;
use SAT\Generated\cfdv40\ComprobanteEmisor;
use SAT\Generated\cfdv40\ComprobanteReceptor;
use SAT\Generated\nomina12\Nomina;
use SAT\Generated\nomina12\NominaDeducciones;
use SAT\Generated\nomina12\NominaDeduccionesDeduccion;
use SAT\Generated\nomina12\NominaEmisor;
use SAT\Generated\nomina12\NominaPercepciones;
use SAT\Generated\nomina12\NominaPercepcionesPercepcion;
use SAT\Generated\nomina12\NominaReceptor;

$cfdi = new Comprobante([
    'Version' => '4.0',
    'Serie' => 'N',
    'Folio' => '2001',
    'Fecha' => date('Y-m-d\\TH:i:s'),
    'SubTotal' => '12000.00',
    'Moneda' => 'MXN',
    'Total' => '10500.00',
    'TipoDeComprobante' => 'N',
    'Exportacion' => '01',
    'LugarExpedicion' => '64000',
]);

$cfdi->addattributes = 'xmlns:cfdi="http://www.sat.gob.mx/cfd/4" xmlns:nomina12="http://www.sat.gob.mx/nomina12"';

$cfdi->Emisor = new ComprobanteEmisor([
    'Rfc' => 'AAA010101AAA',
    'Nombre' => 'EMPRESA DEMO SA DE CV',
    'RegimenFiscal' => '601',
]);

$cfdi->Receptor = new ComprobanteReceptor([
    'Rfc' => 'XEXX010101000',
    'Nombre' => 'EMPLEADO DEMO',
    'DomicilioFiscalReceptor' => '64000',
    'RegimenFiscalReceptor' => '605',
    'UsoCFDI' => 'CN01',
]);

$cfdi->Conceptos = new ComprobanteConceptos();
$cfdi->Conceptos->Concepto[] = new ComprobanteConceptosConcepto([
    'ClaveProdServ' => '84111505',
    'Cantidad' => '1',
    'ClaveUnidad' => 'ACT',
    'Descripcion' => 'Pago de nomina',
    'ValorUnitario' => '12000.00',
    'Importe' => '12000.00',
    'Descuento' => '1500.00',
    'ObjetoImp' => '01',
]);

$nomina = new Nomina([
    'Version' => '1.2',
    'TipoNomina' => 'O',
    'FechaPago' => date('Y-m-d'),
    'FechaInicialPago' => date('Y-m-01'),
    'FechaFinalPago' => date('Y-m-t'),
    'NumDiasPagados' => '15',
    'TotalPercepciones' => '12000.00',
    'TotalDeducciones' => '1500.00',
]);
$nomina->tagName = 'nomina12:Nomina';

$nomina->Emisor = new NominaEmisor([
    'RegistroPatronal' => 'A1234567890',
]);

$nomina->Receptor = new NominaReceptor([
    'Curp' => 'XEXX010101HNEXXXA4',
    'TipoContrato' => '01',
    'TipoRegimen' => '02',
    'NumEmpleado' => '0001',
    'PeriodicidadPago' => '04',
    'ClaveEntFed' => 'NL',
]);

$nomina->Percepciones = new NominaPercepciones([
    'TotalSueldos' => '12000.00',
    'TotalGravado' => '12000.00',
    'TotalExento' => '0.00',
]);
$nomina->Percepciones->Percepcion[] = new NominaPercepcionesPercepcion([
    'TipoPercepcion' => '001',
    'Clave' => 'P001',
    'Concepto' => 'Sueldos, Salarios',
    'ImporteGravado' => '12000.00',
    'ImporteExento' => '0.00',
]);

$nomina->Deducciones = new NominaDeducciones([
    'TotalOtrasDeducciones' => '0.00',
    'TotalImpuestosRetenidos' => '1500.00',
]);
$nomina->Deducciones->Deduccion[] = new NominaDeduccionesDeduccion([
    'TipoDeduccion' => '002',
    'Clave' => 'D001',
    'Concepto' => 'ISR',
    'Importe' => '1500.00',
]);

$complemento = new class() extends ComprobanteComplemento {
    public $Nomina = '';
    public $_sequence = ['Nomina'];
};
$complemento->Nomina = $nomina;

$cfdi->Complemento = $complemento;

header('Content-Type: application/xml; charset=UTF-8');
echo (string)$cfdi;
