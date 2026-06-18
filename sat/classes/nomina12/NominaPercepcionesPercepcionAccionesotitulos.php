<?php

namespace SAT\Generated\nomina12;

class NominaPercepcionesPercepcionAccionesotitulos extends \XMLS
{
    public $tagName = 'AccionesOTitulos';
    public $attributes = array (
  0 => 'ValorMercado',
  1 => 'PrecioAlOtorgarse',
);
    public $_sequence = array (
);

    public $ValorMercado = '';
    public $PrecioAlOtorgarse = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
