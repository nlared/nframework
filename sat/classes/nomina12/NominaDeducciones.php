<?php

namespace SAT\Generated\nomina12;

class NominaDeducciones extends \XMLS
{
    public $tagName = 'Deducciones';
    public $attributes = array (
  0 => 'TotalOtrasDeducciones',
  1 => 'TotalImpuestosRetenidos',
);
    public $_sequence = array (
  0 => 'Deduccion',
);

    public $TotalOtrasDeducciones = '';
    public $TotalImpuestosRetenidos = '';
    public $Deduccion = [];

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
