<?php

namespace SAT\Generated\CartaPorte20;

class CartaporteMercanciasTransportemaritimoContenedor extends \XMLS
{
    public $tagName = 'Contenedor';
    public $attributes = array (
  0 => 'MatriculaContenedor',
  1 => 'TipoContenedor',
  2 => 'NumPrecinto',
);
    public $_sequence = array (
);

    public $MatriculaContenedor = '';
    public $TipoContenedor = '';
    public $NumPrecinto = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
