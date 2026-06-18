<?php

namespace SAT\Generated\CartaPorte31;

class Cartaporte extends \XMLS
{
    public $tagName = 'CartaPorte';
    public $attributes = array (
  0 => 'Version',
  1 => 'IdCCP',
  2 => 'TranspInternac',
  3 => 'EntradaSalidaMerc',
  4 => 'PaisOrigenDestino',
  5 => 'ViaEntradaSalida',
  6 => 'TotalDistRec',
  7 => 'RegistroISTMO',
  8 => 'UbicacionPoloOrigen',
  9 => 'UbicacionPoloDestino',
);
    public $_sequence = array (
  0 => 'RegimenesAduaneros',
  1 => 'Ubicaciones',
  2 => 'Mercancias',
  3 => 'FiguraTransporte',
);

    public $Version = '';
    public $IdCCP = '';
    public $TranspInternac = '';
    public $EntradaSalidaMerc = '';
    public $PaisOrigenDestino = '';
    public $ViaEntradaSalida = '';
    public $TotalDistRec = '';
    public $RegistroISTMO = '';
    public $UbicacionPoloOrigen = '';
    public $UbicacionPoloDestino = '';
    public $RegimenesAduaneros = '';
    public $Ubicaciones = '';
    public $Mercancias = '';
    public $FiguraTransporte = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
