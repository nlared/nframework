<?php

namespace SAT\Generated\CartaPorte;

class CartaporteMercanciasTransporteferroviario extends \XMLS
{
    public $tagName = 'TransporteFerroviario';
    public $attributes = array (
  0 => 'TipoDeServicio',
  1 => 'NombreAseg',
  2 => 'NumPolizaSeguro',
  3 => 'Concesionario',
);
    public $_sequence = array (
  0 => 'DerechosDePaso',
  1 => 'Carro',
);

    public $TipoDeServicio = '';
    public $NombreAseg = '';
    public $NumPolizaSeguro = '';
    public $Concesionario = '';
    public $DerechosDePaso = [];
    public $Carro = [];

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
