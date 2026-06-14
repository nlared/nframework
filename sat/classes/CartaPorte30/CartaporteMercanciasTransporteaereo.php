<?php

namespace SAT\Generated\CartaPorte30;

class CartaporteMercanciasTransporteaereo extends \XMLS
{
    public $tagName = 'TransporteAereo';
    public $attributes = array (
  0 => 'PermSCT',
  1 => 'NumPermisoSCT',
  2 => 'MatriculaAeronave',
  3 => 'NombreAseg',
  4 => 'NumPolizaSeguro',
  5 => 'NumeroGuia',
  6 => 'LugarContrato',
  7 => 'CodigoTransportista',
  8 => 'RFCEmbarcador',
  9 => 'NumRegIdTribEmbarc',
  10 => 'ResidenciaFiscalEmbarc',
  11 => 'NombreEmbarcador',
);
    public $_sequence = array (
);

    public $PermSCT = '';
    public $NumPermisoSCT = '';
    public $MatriculaAeronave = '';
    public $NombreAseg = '';
    public $NumPolizaSeguro = '';
    public $NumeroGuia = '';
    public $LugarContrato = '';
    public $CodigoTransportista = '';
    public $RFCEmbarcador = '';
    public $NumRegIdTribEmbarc = '';
    public $ResidenciaFiscalEmbarc = '';
    public $NombreEmbarcador = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
