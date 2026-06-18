<?php

namespace SAT\Generated\CartaPorte;

class CartaporteMercanciasAutotransportefederalRemolquesRemolque extends \XMLS
{
    public $tagName = 'Remolque';
    public $attributes = array (
  0 => 'SubTipoRem',
  1 => 'Placa',
);
    public $_sequence = array (
);

    public $SubTipoRem = '';
    public $Placa = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
