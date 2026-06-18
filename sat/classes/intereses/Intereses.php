<?php

namespace SAT\Generated\intereses;

class Intereses extends \XMLS
{
    public $tagName = 'Intereses';
    public $attributes = array (
  0 => 'Version',
  1 => 'SistFinanciero',
  2 => 'RetiroAORESRetInt',
  3 => 'OperFinancDerivad',
  4 => 'MontIntNominal',
  5 => 'MontIntReal',
  6 => 'Perdida',
);
    public $_sequence = array (
);

    public $Version = '';
    public $SistFinanciero = '';
    public $RetiroAORESRetInt = '';
    public $OperFinancDerivad = '';
    public $MontIntNominal = '';
    public $MontIntReal = '';
    public $Perdida = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
