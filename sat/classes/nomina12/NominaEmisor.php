<?php

namespace SAT\Generated\nomina12;

class NominaEmisor extends \XMLS
{
    public $tagName = 'Emisor';
    public $attributes = array (
  0 => 'Curp',
  1 => 'RegistroPatronal',
  2 => 'RfcPatronOrigen',
);
    public $_sequence = array (
  0 => 'EntidadSNCF',
);

    public $Curp = '';
    public $RegistroPatronal = '';
    public $RfcPatronOrigen = '';
    public $EntidadSNCF = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
