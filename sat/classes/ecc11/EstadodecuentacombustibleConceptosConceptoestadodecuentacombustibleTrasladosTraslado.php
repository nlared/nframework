<?php

namespace SAT\Generated\ecc11;

class EstadodecuentacombustibleConceptosConceptoestadodecuentacombustibleTrasladosTraslado extends \XMLS
{
    public $tagName = 'Traslado';
    public $attributes = array (
  0 => 'Impuesto',
  1 => 'TasaoCuota',
  2 => 'Importe',
);
    public $_sequence = array (
);

    public $Impuesto = '';
    public $TasaoCuota = '';
    public $Importe = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
