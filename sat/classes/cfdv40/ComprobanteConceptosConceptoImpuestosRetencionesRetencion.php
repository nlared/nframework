<?php

namespace SAT\Generated\cfdv40;

class ComprobanteConceptosConceptoImpuestosRetencionesRetencion extends \XMLS
{
    public $tagName = 'Retencion';
    public $attributes = array (
  0 => 'Base',
  1 => 'Impuesto',
  2 => 'TipoFactor',
  3 => 'TasaOCuota',
  4 => 'Importe',
);
    public $_sequence = array (
);

    public $Base = '';
    public $Impuesto = '';
    public $TipoFactor = '';
    public $TasaOCuota = '';
    public $Importe = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
