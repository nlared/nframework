<?php

namespace SAT\Generated\nomina11;

class NominaDeducciones extends \XMLS
{
    public $tagName = 'Deducciones';
    public $attributes = array (
  0 => 'TotalGravado',
  1 => 'TotalExento',
);
    public $_sequence = array (
  0 => 'Deduccion',
);

    public $TotalGravado = '';
    public $TotalExento = '';
    public $Deduccion = [];

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
