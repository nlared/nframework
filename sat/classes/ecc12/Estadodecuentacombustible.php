<?php

namespace SAT\Generated\ecc12;

class Estadodecuentacombustible extends \XMLS
{
    public $tagName = 'EstadoDeCuentaCombustible';
    public $attributes = array (
  0 => 'Version',
  1 => 'TipoOperacion',
  2 => 'NumeroDeCuenta',
  3 => 'SubTotal',
  4 => 'Total',
);
    public $_sequence = array (
  0 => 'Conceptos',
);

    public $Version = '';
    public $TipoOperacion = '';
    public $NumeroDeCuenta = '';
    public $SubTotal = '';
    public $Total = '';
    public $Conceptos = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
