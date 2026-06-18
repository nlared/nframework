<?php

namespace SAT\Generated\terceros11;

class PorcuentadetercerosImpuestosRetencionesRetencion extends \XMLS
{
    public $tagName = 'Retencion';
    public $attributes = array (
  0 => 'impuesto',
  1 => 'importe',
);
    public $_sequence = array (
);

    public $impuesto = '';
    public $importe = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
