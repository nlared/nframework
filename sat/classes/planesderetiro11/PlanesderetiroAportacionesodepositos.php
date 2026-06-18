<?php

namespace SAT\Generated\planesderetiro11;

class PlanesderetiroAportacionesodepositos extends \XMLS
{
    public $tagName = 'AportacionesODepositos';
    public $attributes = array (
  0 => 'TipoAportacionODeposito',
  1 => 'MontAportODep',
  2 => 'RFCFiduciaria',
);
    public $_sequence = array (
);

    public $TipoAportacionODeposito = '';
    public $MontAportODep = '';
    public $RFCFiduciaria = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
