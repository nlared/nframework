<?php

namespace SAT\Generated\CartaPorte31;

class CartaporteMercanciasTransporteferroviarioCarroContenedor extends \XMLS
{
    public $tagName = 'Contenedor';
    public $attributes = array (
  0 => 'TipoContenedor',
  1 => 'PesoContenedorVacio',
  2 => 'PesoNetoMercancia',
);
    public $_sequence = array (
);

    public $TipoContenedor = '';
    public $PesoContenedorVacio = '';
    public $PesoNetoMercancia = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
