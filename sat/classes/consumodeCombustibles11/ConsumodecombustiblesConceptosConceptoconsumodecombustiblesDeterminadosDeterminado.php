<?php

namespace SAT\Generated\consumodeCombustibles11;

class ConsumodecombustiblesConceptosConceptoconsumodecombustiblesDeterminadosDeterminado extends \XMLS
{
    public $tagName = 'Determinado';
    public $attributes = array (
  0 => 'impuesto',
  1 => 'tasaOCuota',
  2 => 'importe',
);
    public $_sequence = array (
);

    public $impuesto = '';
    public $tasaOCuota = '';
    public $importe = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
