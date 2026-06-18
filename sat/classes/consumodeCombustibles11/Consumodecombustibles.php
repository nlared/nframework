<?php

namespace SAT\Generated\consumodeCombustibles11;

class Consumodecombustibles extends \XMLS
{
    public $tagName = 'ConsumoDeCombustibles';
    public $attributes = array (
  0 => 'version',
  1 => 'tipoOperacion',
  2 => 'numeroDeCuenta',
  3 => 'subTotal',
  4 => 'total',
);
    public $_sequence = array (
  0 => 'Conceptos',
);

    public $version = '';
    public $tipoOperacion = '';
    public $numeroDeCuenta = '';
    public $subTotal = '';
    public $total = '';
    public $Conceptos = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
