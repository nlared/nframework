<?php

namespace SAT\Generated\CartaPorte;

class CartaporteMercanciasMercancia extends \XMLS
{
    public $tagName = 'Mercancia';
    public $attributes = array (
  0 => 'BienesTransp',
  1 => 'ClaveSTCC',
  2 => 'Descripcion',
  3 => 'Cantidad',
  4 => 'ClaveUnidad',
  5 => 'Unidad',
  6 => 'Dimensiones',
  7 => 'MaterialPeligroso',
  8 => 'CveMaterialPeligroso',
  9 => 'Embalaje',
  10 => 'DescripEmbalaje',
  11 => 'PesoEnKg',
  12 => 'ValorMercancia',
  13 => 'Moneda',
  14 => 'FraccionArancelaria',
  15 => 'UUIDComercioExt',
);
    public $_sequence = array (
  0 => 'CantidadTransporta',
  1 => 'DetalleMercancia',
);

    public $BienesTransp = '';
    public $ClaveSTCC = '';
    public $Descripcion = '';
    public $Cantidad = '';
    public $ClaveUnidad = '';
    public $Unidad = '';
    public $Dimensiones = '';
    public $MaterialPeligroso = '';
    public $CveMaterialPeligroso = '';
    public $Embalaje = '';
    public $DescripEmbalaje = '';
    public $PesoEnKg = '';
    public $ValorMercancia = '';
    public $Moneda = '';
    public $FraccionArancelaria = '';
    public $UUIDComercioExt = '';
    public $CantidadTransporta = [];
    public $DetalleMercancia = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
