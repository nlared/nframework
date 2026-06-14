<?php

namespace SAT\Generated\CartaPorte20;

class CartaporteMercanciasMercanciaGuiasidentificacion extends \XMLS
{
    public $tagName = 'GuiasIdentificacion';
    public $attributes = array (
  0 => 'NumeroGuiaIdentificacion',
  1 => 'DescripGuiaIdentificacion',
  2 => 'PesoGuiaIdentificacion',
);
    public $_sequence = array (
);

    public $NumeroGuiaIdentificacion = '';
    public $DescripGuiaIdentificacion = '';
    public $PesoGuiaIdentificacion = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
