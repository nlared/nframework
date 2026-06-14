<?php

namespace SAT\Generated\cfdv33;

class ComprobanteImpuestosRetencionesRetencion extends \XMLS
{
    public $tagName = 'Retencion';
    public $attributes = array (
  0 => 'Impuesto',
  1 => 'Importe',
);
    public $_sequence = array (
);

    public $Impuesto = '';
    public $Importe = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
