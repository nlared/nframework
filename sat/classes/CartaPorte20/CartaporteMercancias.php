<?php

namespace SAT\Generated\CartaPorte20;

class CartaporteMercancias extends \XMLS
{
    public $tagName = 'Mercancias';
    public $attributes = array (
  0 => 'PesoBrutoTotal',
  1 => 'UnidadPeso',
  2 => 'PesoNetoTotal',
  3 => 'NumTotalMercancias',
  4 => 'CargoPorTasacion',
);
    public $_sequence = array (
  0 => 'Mercancia',
  1 => 'Autotransporte',
  2 => 'TransporteMaritimo',
  3 => 'TransporteAereo',
  4 => 'TransporteFerroviario',
);

    public $PesoBrutoTotal = '';
    public $UnidadPeso = '';
    public $PesoNetoTotal = '';
    public $NumTotalMercancias = '';
    public $CargoPorTasacion = '';
    public $Mercancia = [];
    public $Autotransporte = '';
    public $TransporteMaritimo = '';
    public $TransporteAereo = '';
    public $TransporteFerroviario = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
