<?php

namespace SAT\Generated\CartaPorte20;

class CartaporteMercanciasAutotransporteIdentificacionvehicular extends \XMLS
{
    public $tagName = 'IdentificacionVehicular';
    public $attributes = array (
  0 => 'ConfigVehicular',
  1 => 'PlacaVM',
  2 => 'AnioModeloVM',
);
    public $_sequence = array (
);

    public $ConfigVehicular = '';
    public $PlacaVM = '';
    public $AnioModeloVM = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
