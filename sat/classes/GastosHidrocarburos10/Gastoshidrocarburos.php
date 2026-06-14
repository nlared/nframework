<?php

namespace SAT\Generated\GastosHidrocarburos10;

class Gastoshidrocarburos extends \XMLS
{
    public $tagName = 'GastosHidrocarburos';
    public $attributes = array (
  0 => 'Version',
  1 => 'NumeroContrato',
  2 => 'AreaContractual',
);
    public $_sequence = array (
  0 => 'Erogacion',
);

    public $Version = '';
    public $NumeroContrato = '';
    public $AreaContractual = '';
    public $Erogacion = [];

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
