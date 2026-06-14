<?php

namespace SAT\Generated\CartaPorte31;

class CartaporteMercanciasMercanciaDocumentacionaduanera extends \XMLS
{
    public $tagName = 'DocumentacionAduanera';
    public $attributes = array (
  0 => 'TipoDocumento',
  1 => 'NumPedimento',
  2 => 'IdentDocAduanero',
  3 => 'RFCImpo',
);
    public $_sequence = array (
);

    public $TipoDocumento = '';
    public $NumPedimento = '';
    public $IdentDocAduanero = '';
    public $RFCImpo = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
