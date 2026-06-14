<?php

namespace SAT\Generated\renovacionysustitucionvehiculos;

class RenovacionysustitucionvehiculosDecretorenovvehicular extends \XMLS
{
    public $tagName = 'DecretoRenovVehicular';
    public $attributes = array (
  0 => 'VehEnaj',
);
    public $_sequence = array (
  0 => 'VehiculosUsadosEnajenadoPermAlFab',
  1 => 'VehiculoNuvoSemEnajenadoFabAlPerm',
);

    public $VehEnaj = '';
    public $VehiculosUsadosEnajenadoPermAlFab = [];
    public $VehiculoNuvoSemEnajenadoFabAlPerm = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
