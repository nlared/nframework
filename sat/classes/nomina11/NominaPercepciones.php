<?php

namespace SAT\Generated\nomina11;

class NominaPercepciones extends \XMLS
{
    public $tagName = 'Percepciones';
    public $attributes = array (
  0 => 'TotalGravado',
  1 => 'TotalExento',
);
    public $_sequence = array (
  0 => 'Percepcion',
);

    public $TotalGravado = '';
    public $TotalExento = '';
    public $Percepcion = [];

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
