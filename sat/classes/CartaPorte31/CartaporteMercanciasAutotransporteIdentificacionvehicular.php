<?php

namespace SAT\Generated\CartaPorte31;

class CartaporteMercanciasAutotransporteIdentificacionvehicular extends \XMLS
{
    public $tagName = 'IdentificacionVehicular';
    public $attributes = array (
  0 => 'ConfigVehicular',
  1 => 'PesoBrutoVehicular',
  2 => 'PlacaVM',
  3 => 'AnioModeloVM',
);
    public $_sequence = array (
);

    public $ConfigVehicular = '';
    public $PesoBrutoVehicular = '';
    public $PlacaVM = '';
    public $AnioModeloVM = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
