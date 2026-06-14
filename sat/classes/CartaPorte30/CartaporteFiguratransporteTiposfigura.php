<?php

namespace SAT\Generated\CartaPorte30;

class CartaporteFiguratransporteTiposfigura extends \XMLS
{
    public $tagName = 'TiposFigura';
    public $attributes = array (
  0 => 'TipoFigura',
  1 => 'RFCFigura',
  2 => 'NumLicencia',
  3 => 'NombreFigura',
  4 => 'NumRegIdTribFigura',
  5 => 'ResidenciaFiscalFigura',
);
    public $_sequence = array (
  0 => 'PartesTransporte',
  1 => 'Domicilio',
);

    public $TipoFigura = '';
    public $RFCFigura = '';
    public $NumLicencia = '';
    public $NombreFigura = '';
    public $NumRegIdTribFigura = '';
    public $ResidenciaFiscalFigura = '';
    public $PartesTransporte = [];
    public $Domicilio = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
