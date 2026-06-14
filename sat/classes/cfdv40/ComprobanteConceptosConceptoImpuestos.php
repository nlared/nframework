<?php

namespace SAT\Generated\cfdv40;

class ComprobanteConceptosConceptoImpuestos extends \XMLS
{
    public $tagName = 'Impuestos';
    public $attributes = array (
);
    public $_sequence = array (
  0 => 'Traslados',
  1 => 'Retenciones',
);

    public $Traslados = '';
    public $Retenciones = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
