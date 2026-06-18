<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/class.XMLS.php';
require_once __DIR__ . '/../classes/nomina12/Nomina.php';
require_once __DIR__ . '/../classes/nomina12/NominaEmisor.php';
require_once __DIR__ . '/../classes/nomina12/NominaReceptor.php';
require_once __DIR__ . '/../classes/nomina12/NominaPercepciones.php';
require_once __DIR__ . '/../classes/nomina12/NominaPercepcionesPercepcion.php';
require_once __DIR__ . '/../classes/nomina12/NominaDeducciones.php';
require_once __DIR__ . '/../classes/nomina12/NominaDeduccionesDeduccion.php';

use SAT\Generated\nomina12\Nomina;
use SAT\Generated\nomina12\NominaDeducciones;
use SAT\Generated\nomina12\NominaDeduccionesDeduccion;
use SAT\Generated\nomina12\NominaEmisor;
use SAT\Generated\nomina12\NominaPercepciones;
use SAT\Generated\nomina12\NominaPercepcionesPercepcion;
use SAT\Generated\nomina12\NominaReceptor;

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

header('Content-Type: application/xml; charset=UTF-8');
echo (string)$nomina;
