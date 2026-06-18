<?php

namespace SAT\Generated\renovacionysustitucionvehiculos;

class RenovacionysustitucionvehiculosDecretosustitvehicular extends \XMLS
{
    public $tagName = 'DecretoSustitVehicular';
    public $attributes = array (
  0 => 'VehEnaj',
);
    public $_sequence = array (
  0 => 'VehiculoUsadoEnajenadoPermAlFab',
  1 => 'VehiculoNuvoSemEnajenadoFabAlPerm',
);

    public $VehEnaj = '';
    public $VehiculoUsadoEnajenadoPermAlFab = '';
    public $VehiculoNuvoSemEnajenadoFabAlPerm = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
