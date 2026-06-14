<?php

namespace SAT\Generated\cfdv3;

class ComprobanteImpuestos extends \XMLS
{
    public $tagName = 'Impuestos';
    public $attributes = array (
  0 => 'totalImpuestosRetenidos',
  1 => 'totalImpuestosTrasladados',
);
    public $_sequence = array (
  0 => 'Retenciones',
  1 => 'Traslados',
);

    public $totalImpuestosRetenidos = '';
    public $totalImpuestosTrasladados = '';
    public $Retenciones = '';
    public $Traslados = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
