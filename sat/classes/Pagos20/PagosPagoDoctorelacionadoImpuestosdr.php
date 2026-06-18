<?php

namespace SAT\Generated\Pagos20;

class PagosPagoDoctorelacionadoImpuestosdr extends \XMLS
{
    public $tagName = 'ImpuestosDR';
    public $attributes = array (
);
    public $_sequence = array (
  0 => 'RetencionesDR',
  1 => 'TrasladosDR',
);

    public $RetencionesDR = '';
    public $TrasladosDR = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
