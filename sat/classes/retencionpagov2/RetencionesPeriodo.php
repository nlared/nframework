<?php

namespace SAT\Generated\retencionpagov2;

class RetencionesPeriodo extends \XMLS
{
    public $tagName = 'Periodo';
    public $attributes = array (
  0 => 'MesIni',
  1 => 'MesFin',
  2 => 'Ejercicio',
);
    public $_sequence = array (
);

    public $MesIni = '';
    public $MesFin = '';
    public $Ejercicio = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
