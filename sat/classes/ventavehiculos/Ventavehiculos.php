<?php

namespace SAT\Generated\ventavehiculos;

class Ventavehiculos extends \XMLS
{
    public $tagName = 'VentaVehiculos';
    public $attributes = array (
  0 => 'version',
  1 => 'ClaveVehicular',
);
    public $_sequence = array (
);

    public $version = '';
    public $ClaveVehicular = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
