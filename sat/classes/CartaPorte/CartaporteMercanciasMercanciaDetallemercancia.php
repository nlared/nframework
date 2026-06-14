<?php

namespace SAT\Generated\CartaPorte;

class CartaporteMercanciasMercanciaDetallemercancia extends \XMLS
{
    public $tagName = 'DetalleMercancia';
    public $attributes = array (
  0 => 'UnidadPeso',
  1 => 'PesoBruto',
  2 => 'PesoNeto',
  3 => 'PesoTara',
  4 => 'NumPiezas',
);
    public $_sequence = array (
);

    public $UnidadPeso = '';
    public $PesoBruto = '';
    public $PesoNeto = '';
    public $PesoTara = '';
    public $NumPiezas = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
