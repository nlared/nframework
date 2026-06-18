<?php

namespace SAT\Generated\nomina11;

class Nomina extends \XMLS
{
    public $tagName = 'Nomina';
    public $attributes = array (
  0 => 'Version',
  1 => 'RegistroPatronal',
  2 => 'NumEmpleado',
  3 => 'CURP',
  4 => 'TipoRegimen',
  5 => 'NumSeguridadSocial',
  6 => 'FechaPago',
  7 => 'FechaInicialPago',
  8 => 'FechaFinalPago',
  9 => 'NumDiasPagados',
  10 => 'Departamento',
  11 => 'CLABE',
  12 => 'Banco',
  13 => 'FechaInicioRelLaboral',
  14 => 'Antiguedad',
  15 => 'Puesto',
  16 => 'TipoContrato',
  17 => 'TipoJornada',
  18 => 'PeriodicidadPago',
  19 => 'SalarioBaseCotApor',
  20 => 'RiesgoPuesto',
  21 => 'SalarioDiarioIntegrado',
);
    public $_sequence = array (
  0 => 'Percepciones',
  1 => 'Deducciones',
  2 => 'Incapacidades',
  3 => 'HorasExtras',
);

    public $Version = '';
    public $RegistroPatronal = '';
    public $NumEmpleado = '';
    public $CURP = '';
    public $TipoRegimen = '';
    public $NumSeguridadSocial = '';
    public $FechaPago = '';
    public $FechaInicialPago = '';
    public $FechaFinalPago = '';
    public $NumDiasPagados = '';
    public $Departamento = '';
    public $CLABE = '';
    public $Banco = '';
    public $FechaInicioRelLaboral = '';
    public $Antiguedad = '';
    public $Puesto = '';
    public $TipoContrato = '';
    public $TipoJornada = '';
    public $PeriodicidadPago = '';
    public $SalarioBaseCotApor = '';
    public $RiesgoPuesto = '';
    public $SalarioDiarioIntegrado = '';
    public $Percepciones = '';
    public $Deducciones = '';
    public $Incapacidades = '';
    public $HorasExtras = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
