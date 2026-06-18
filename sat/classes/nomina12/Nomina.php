<?php

namespace SAT\Generated\nomina12;

class Nomina extends \XMLS
{
    public $tagName = 'Nomina';
    public $attributes = array (
  0 => 'Version',
  1 => 'TipoNomina',
  2 => 'FechaPago',
  3 => 'FechaInicialPago',
  4 => 'FechaFinalPago',
  5 => 'NumDiasPagados',
  6 => 'TotalPercepciones',
  7 => 'TotalDeducciones',
  8 => 'TotalOtrosPagos',
);
    public $_sequence = array (
  0 => 'Emisor',
  1 => 'Receptor',
  2 => 'Percepciones',
  3 => 'Deducciones',
  4 => 'OtrosPagos',
  5 => 'Incapacidades',
);

    public $Version = '';
    public $TipoNomina = '';
    public $FechaPago = '';
    public $FechaInicialPago = '';
    public $FechaFinalPago = '';
    public $NumDiasPagados = '';
    public $TotalPercepciones = '';
    public $TotalDeducciones = '';
    public $TotalOtrosPagos = '';
    public $Emisor = '';
    public $Receptor = '';
    public $Percepciones = '';
    public $Deducciones = '';
    public $OtrosPagos = '';
    public $Incapacidades = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
