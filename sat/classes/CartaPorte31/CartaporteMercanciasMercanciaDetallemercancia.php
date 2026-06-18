<?php

namespace SAT\Generated\CartaPorte31;

class CartaporteMercanciasMercanciaDetallemercancia extends \XMLS
{
    public $tagName = 'DetalleMercancia';
    public $attributes = array (
  0 => 'UnidadPesoMerc',
  1 => 'PesoBruto',
  2 => 'PesoNeto',
  3 => 'PesoTara',
  4 => 'NumPiezas',
);
    public $_sequence = array (
);

    public $UnidadPesoMerc = '';
    public $PesoBruto = '';
    public $PesoNeto = '';
    public $PesoTara = '';
    public $NumPiezas = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
