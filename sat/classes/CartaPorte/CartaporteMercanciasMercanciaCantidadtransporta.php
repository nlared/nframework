<?php

namespace SAT\Generated\CartaPorte;

class CartaporteMercanciasMercanciaCantidadtransporta extends \XMLS
{
    public $tagName = 'CantidadTransporta';
    public $attributes = array (
  0 => 'Cantidad',
  1 => 'IDOrigen',
  2 => 'IDDestino',
  3 => 'CvesTransporte',
);
    public $_sequence = array (
);

    public $Cantidad = '';
    public $IDOrigen = '';
    public $IDDestino = '';
    public $CvesTransporte = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
