<?php

namespace SAT\Generated\cfdv33;

class ComprobanteImpuestos extends \XMLS
{
    public $tagName = 'Impuestos';
    public $attributes = array (
  0 => 'TotalImpuestosRetenidos',
  1 => 'TotalImpuestosTrasladados',
);
    public $_sequence = array (
  0 => 'Retenciones',
  1 => 'Traslados',
);

    public $TotalImpuestosRetenidos = '';
    public $TotalImpuestosTrasladados = '';
    public $Retenciones = '';
    public $Traslados = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
