<?php

namespace SAT\Generated\CartaPorte20;

class CartaporteMercanciasTransporteferroviario extends \XMLS
{
    public $tagName = 'TransporteFerroviario';
    public $attributes = array (
  0 => 'TipoDeServicio',
  1 => 'TipoDeTrafico',
  2 => 'NombreAseg',
  3 => 'NumPolizaSeguro',
);
    public $_sequence = array (
  0 => 'DerechosDePaso',
  1 => 'Carro',
);

    public $TipoDeServicio = '';
    public $TipoDeTrafico = '';
    public $NombreAseg = '';
    public $NumPolizaSeguro = '';
    public $DerechosDePaso = [];
    public $Carro = [];

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
