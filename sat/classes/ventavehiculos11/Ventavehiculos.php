<?php

namespace SAT\Generated\ventavehiculos11;

class Ventavehiculos extends \XMLS
{
    public $tagName = 'VentaVehiculos';
    public $attributes = array (
  0 => 'version',
  1 => 'ClaveVehicular',
  2 => 'Niv',
);
    public $_sequence = array (
);

    public $version = '';
    public $ClaveVehicular = '';
    public $Niv = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
