<?php

namespace SAT\Generated\consumodecombustibles;

class ConsumodecombustiblesConceptosConceptoconsumodecombustiblesDeterminadosDeterminado extends \XMLS
{
    public $tagName = 'Determinado';
    public $attributes = array (
  0 => 'impuesto',
  1 => 'tasa',
  2 => 'importe',
);
    public $_sequence = array (
);

    public $impuesto = '';
    public $tasa = '';
    public $importe = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
