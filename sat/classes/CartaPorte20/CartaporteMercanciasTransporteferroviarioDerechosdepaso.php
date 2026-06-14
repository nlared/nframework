<?php

namespace SAT\Generated\CartaPorte20;

class CartaporteMercanciasTransporteferroviarioDerechosdepaso extends \XMLS
{
    public $tagName = 'DerechosDePaso';
    public $attributes = array (
  0 => 'TipoDerechoDePaso',
  1 => 'KilometrajePagado',
);
    public $_sequence = array (
);

    public $TipoDerechoDePaso = '';
    public $KilometrajePagado = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
