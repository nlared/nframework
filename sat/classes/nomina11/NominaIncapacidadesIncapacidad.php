<?php

namespace SAT\Generated\nomina11;

class NominaIncapacidadesIncapacidad extends \XMLS
{
    public $tagName = 'Incapacidad';
    public $attributes = array (
  0 => 'DiasIncapacidad',
  1 => 'TipoIncapacidad',
  2 => 'Descuento',
);
    public $_sequence = array (
);

    public $DiasIncapacidad = '';
    public $TipoIncapacidad = '';
    public $Descuento = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
