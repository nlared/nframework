<?php

namespace SAT\Generated\Pagos20;

class PagosPagoImpuestospTrasladospTrasladop extends \XMLS
{
    public $tagName = 'TrasladoP';
    public $attributes = array (
  0 => 'BaseP',
  1 => 'ImpuestoP',
  2 => 'TipoFactorP',
  3 => 'TasaOCuotaP',
  4 => 'ImporteP',
);
    public $_sequence = array (
);

    public $BaseP = '';
    public $ImpuestoP = '';
    public $TipoFactorP = '';
    public $TasaOCuotaP = '';
    public $ImporteP = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
