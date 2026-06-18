<?php

namespace SAT\Generated\cfdv33;

class ComprobanteImpuestosTrasladosTraslado extends \XMLS
{
    public $tagName = 'Traslado';
    public $attributes = array (
  0 => 'Impuesto',
  1 => 'TipoFactor',
  2 => 'TasaOCuota',
  3 => 'Importe',
);
    public $_sequence = array (
);

    public $Impuesto = '';
    public $TipoFactor = '';
    public $TasaOCuota = '';
    public $Importe = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
