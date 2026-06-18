<?php

namespace SAT\Generated\nomina12;

class NominaIncapacidadesIncapacidad extends \XMLS
{
    public $tagName = 'Incapacidad';
    public $attributes = array (
  0 => 'DiasIncapacidad',
  1 => 'TipoIncapacidad',
  2 => 'ImporteMonetario',
);
    public $_sequence = array (
);

    public $DiasIncapacidad = '';
    public $TipoIncapacidad = '';
    public $ImporteMonetario = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
