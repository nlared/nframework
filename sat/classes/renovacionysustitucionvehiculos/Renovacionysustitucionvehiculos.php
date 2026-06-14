<?php

namespace SAT\Generated\renovacionysustitucionvehiculos;

class Renovacionysustitucionvehiculos extends \XMLS
{
    public $tagName = 'renovacionysustitucionvehiculos';
    public $attributes = array (
  0 => 'Version',
  1 => 'TipoDeDecreto',
);
    public $_sequence = array (
  0 => 'DecretoRenovVehicular',
  1 => 'DecretoSustitVehicular',
);

    public $Version = '';
    public $TipoDeDecreto = '';
    public $DecretoRenovVehicular = '';
    public $DecretoSustitVehicular = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
