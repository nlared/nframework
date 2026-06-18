<?php

namespace SAT\Generated\fideicomisonoempresarial;

class Fideicomisonoempresarial extends \XMLS
{
    public $tagName = 'Fideicomisonoempresarial';
    public $attributes = array (
  0 => 'Version',
);
    public $_sequence = array (
  0 => 'IngresosOEntradas',
  1 => 'DeduccOSalidas',
  2 => 'RetEfectFideicomiso',
);

    public $Version = '';
    public $IngresosOEntradas = '';
    public $DeduccOSalidas = '';
    public $RetEfectFideicomiso = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
