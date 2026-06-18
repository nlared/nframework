<?php

namespace SAT\Generated\terceros11;

class PorcuentadetercerosImpuestos extends \XMLS
{
    public $tagName = 'Impuestos';
    public $attributes = array (
);
    public $_sequence = array (
  0 => 'Retenciones',
  1 => 'Traslados',
);

    public $Retenciones = '';
    public $Traslados = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
