<?php

namespace SAT\Generated\nomina12;

class NominaPercepcionesJubilacionpensionretiro extends \XMLS
{
    public $tagName = 'JubilacionPensionRetiro';
    public $attributes = array (
  0 => 'TotalUnaExhibicion',
  1 => 'TotalParcialidad',
  2 => 'MontoDiario',
  3 => 'IngresoAcumulable',
  4 => 'IngresoNoAcumulable',
);
    public $_sequence = array (
);

    public $TotalUnaExhibicion = '';
    public $TotalParcialidad = '';
    public $MontoDiario = '';
    public $IngresoAcumulable = '';
    public $IngresoNoAcumulable = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
