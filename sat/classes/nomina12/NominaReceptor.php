<?php

namespace SAT\Generated\nomina12;

class NominaReceptor extends \XMLS
{
    public $tagName = 'Receptor';
    public $attributes = array (
  0 => 'Curp',
  1 => 'NumSeguridadSocial',
  2 => 'FechaInicioRelLaboral',
  3 => 'Antig__edad',
  4 => 'TipoContrato',
  5 => 'Sindicalizado',
  6 => 'TipoJornada',
  7 => 'TipoRegimen',
  8 => 'NumEmpleado',
  9 => 'Departamento',
  10 => 'Puesto',
  11 => 'RiesgoPuesto',
  12 => 'PeriodicidadPago',
  13 => 'Banco',
  14 => 'CuentaBancaria',
  15 => 'SalarioBaseCotApor',
  16 => 'SalarioDiarioIntegrado',
  17 => 'ClaveEntFed',
);
    public $_sequence = array (
  0 => 'SubContratacion',
);

    public $Curp = '';
    public $NumSeguridadSocial = '';
    public $FechaInicioRelLaboral = '';
    public $Antig__edad = '';
    public $TipoContrato = '';
    public $Sindicalizado = '';
    public $TipoJornada = '';
    public $TipoRegimen = '';
    public $NumEmpleado = '';
    public $Departamento = '';
    public $Puesto = '';
    public $RiesgoPuesto = '';
    public $PeriodicidadPago = '';
    public $Banco = '';
    public $CuentaBancaria = '';
    public $SalarioBaseCotApor = '';
    public $SalarioDiarioIntegrado = '';
    public $ClaveEntFed = '';
    public $SubContratacion = [];

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
