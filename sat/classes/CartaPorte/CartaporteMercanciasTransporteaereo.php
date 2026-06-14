<?php

namespace SAT\Generated\CartaPorte;

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
  7 => 'RFCTransportista',
  8 => 'CodigoTransportista',
  9 => 'NumRegIdTribTranspor',
  10 => 'ResidenciaFiscalTranspor',
  11 => 'NombreTransportista',
  12 => 'RFCEmbarcador',
  13 => 'NumRegIdTribEmbarc',
  14 => 'ResidenciaFiscalEmbarc',
  15 => 'NombreEmbarcador',
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
    public $RFCTransportista = '';
    public $CodigoTransportista = '';
    public $NumRegIdTribTranspor = '';
    public $ResidenciaFiscalTranspor = '';
    public $NombreTransportista = '';
    public $RFCEmbarcador = '';
    public $NumRegIdTribEmbarc = '';
    public $ResidenciaFiscalEmbarc = '';
    public $NombreEmbarcador = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
