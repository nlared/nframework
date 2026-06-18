<?php

namespace SAT\Generated\CartaPorte30;

class Cartaporte extends \XMLS
{
    public $tagName = 'CartaPorte';
    public $attributes = array (
  0 => 'Version',
  1 => 'IdCCP',
  2 => 'TranspInternac',
  3 => 'RegimenAduanero',
  4 => 'EntradaSalidaMerc',
  5 => 'PaisOrigenDestino',
  6 => 'ViaEntradaSalida',
  7 => 'TotalDistRec',
  8 => 'RegistroISTMO',
  9 => 'UbicacionPoloOrigen',
  10 => 'UbicacionPoloDestino',
);
    public $_sequence = array (
  0 => 'Ubicaciones',
  1 => 'Mercancias',
  2 => 'FiguraTransporte',
);

    public $Version = '';
    public $IdCCP = '';
    public $TranspInternac = '';
    public $RegimenAduanero = '';
    public $EntradaSalidaMerc = '';
    public $PaisOrigenDestino = '';
    public $ViaEntradaSalida = '';
    public $TotalDistRec = '';
    public $RegistroISTMO = '';
    public $UbicacionPoloOrigen = '';
    public $UbicacionPoloDestino = '';
    public $Ubicaciones = '';
    public $Mercancias = '';
    public $FiguraTransporte = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
