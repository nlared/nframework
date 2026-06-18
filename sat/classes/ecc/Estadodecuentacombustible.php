<?php

namespace SAT\Generated\ecc;

class Estadodecuentacombustible extends \XMLS
{
    public $tagName = 'EstadoDeCuentaCombustible';
    public $attributes = array (
  0 => 'tipoOperacion',
  1 => 'numeroDeCuenta',
  2 => 'subTotal',
  3 => 'total',
);
    public $_sequence = array (
  0 => 'Conceptos',
);

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
