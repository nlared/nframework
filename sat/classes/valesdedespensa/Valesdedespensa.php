<?php

namespace SAT\Generated\valesdedespensa;

class Valesdedespensa extends \XMLS
{
    public $tagName = 'ValesDeDespensa';
    public $attributes = array (
  0 => 'version',
  1 => 'tipoOperacion',
  2 => 'registroPatronal',
  3 => 'numeroDeCuenta',
  4 => 'total',
);
    public $_sequence = array (
  0 => 'Conceptos',
);

    public $version = '';
    public $tipoOperacion = '';
    public $registroPatronal = '';
    public $numeroDeCuenta = '';
    public $total = '';
    public $Conceptos = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
