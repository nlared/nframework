<?php

namespace SAT\Generated\ComercioExterior20;

class ComercioexteriorPropietario extends \XMLS
{
    public $tagName = 'Propietario';
    public $attributes = array (
  0 => 'NumRegIdTrib',
  1 => 'ResidenciaFiscal',
);
    public $_sequence = array (
);

    public $NumRegIdTrib = '';
    public $ResidenciaFiscal = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
