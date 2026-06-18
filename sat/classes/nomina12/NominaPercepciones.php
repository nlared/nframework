<?php

namespace SAT\Generated\nomina12;

class NominaPercepciones extends \XMLS
{
    public $tagName = 'Percepciones';
    public $attributes = array (
  0 => 'TotalSueldos',
  1 => 'TotalSeparacionIndemnizacion',
  2 => 'TotalJubilacionPensionRetiro',
  3 => 'TotalGravado',
  4 => 'TotalExento',
);
    public $_sequence = array (
  0 => 'Percepcion',
  1 => 'JubilacionPensionRetiro',
  2 => 'SeparacionIndemnizacion',
);

    public $TotalSueldos = '';
    public $TotalSeparacionIndemnizacion = '';
    public $TotalJubilacionPensionRetiro = '';
    public $TotalGravado = '';
    public $TotalExento = '';
    public $Percepcion = [];
    public $JubilacionPensionRetiro = '';
    public $SeparacionIndemnizacion = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
