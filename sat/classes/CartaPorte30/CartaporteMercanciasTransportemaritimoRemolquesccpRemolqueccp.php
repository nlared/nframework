<?php

namespace SAT\Generated\CartaPorte30;

class CartaporteMercanciasTransportemaritimoRemolquesccpRemolqueccp extends \XMLS
{
    public $tagName = 'RemolqueCCP';
    public $attributes = array (
  0 => 'SubTipoRemCCP',
  1 => 'PlacaCCP',
);
    public $_sequence = array (
);

    public $SubTipoRemCCP = '';
    public $PlacaCCP = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
