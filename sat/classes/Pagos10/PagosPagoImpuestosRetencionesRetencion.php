<?php

namespace SAT\Generated\Pagos10;

class PagosPagoImpuestosRetencionesRetencion extends \XMLS
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
