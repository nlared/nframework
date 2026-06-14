<?php

namespace SAT\Generated\ecc12;

class EstadodecuentacombustibleConceptosConceptoestadodecuentacombustibleTrasladosTraslado extends \XMLS
{
    public $tagName = 'Traslado';
    public $attributes = array (
  0 => 'Impuesto',
  1 => 'TasaOCuota',
  2 => 'Importe',
);
    public $_sequence = array (
);

    public $Impuesto = '';
    public $TasaOCuota = '';
    public $Importe = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
