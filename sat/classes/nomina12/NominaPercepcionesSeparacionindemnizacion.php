<?php

namespace SAT\Generated\nomina12;

class NominaPercepcionesSeparacionindemnizacion extends \XMLS
{
    public $tagName = 'SeparacionIndemnizacion';
    public $attributes = array (
  0 => 'TotalPagado',
  1 => 'NumA__osServicio',
  2 => 'UltimoSueldoMensOrd',
  3 => 'IngresoAcumulable',
  4 => 'IngresoNoAcumulable',
);
    public $_sequence = array (
);

    public $TotalPagado = '';
    public $NumA__osServicio = '';
    public $UltimoSueldoMensOrd = '';
    public $IngresoAcumulable = '';
    public $IngresoNoAcumulable = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
