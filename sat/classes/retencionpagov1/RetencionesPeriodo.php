<?php

namespace SAT\Generated\retencionpagov1;

class RetencionesPeriodo extends \XMLS
{
    public $tagName = 'Periodo';
    public $attributes = array (
  0 => 'MesIni',
  1 => 'MesFin',
  2 => 'Ejerc',
);
    public $_sequence = array (
);

    public $MesIni = '';
    public $MesFin = '';
    public $Ejerc = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
