<?php

namespace SAT\Generated\CartaPorte31;

class CartaporteMercanciasTransporteferroviarioCarro extends \XMLS
{
    public $tagName = 'Carro';
    public $attributes = array (
  0 => 'TipoCarro',
  1 => 'MatriculaCarro',
  2 => 'GuiaCarro',
  3 => 'ToneladasNetasCarro',
);
    public $_sequence = array (
  0 => 'Contenedor',
);

    public $TipoCarro = '';
    public $MatriculaCarro = '';
    public $GuiaCarro = '';
    public $ToneladasNetasCarro = '';
    public $Contenedor = [];

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
