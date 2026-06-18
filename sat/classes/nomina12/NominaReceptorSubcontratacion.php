<?php

namespace SAT\Generated\nomina12;

class NominaReceptorSubcontratacion extends \XMLS
{
    public $tagName = 'SubContratacion';
    public $attributes = array (
  0 => 'RfcLabora',
  1 => 'PorcentajeTiempo',
);
    public $_sequence = array (
);

    public $RfcLabora = '';
    public $PorcentajeTiempo = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
