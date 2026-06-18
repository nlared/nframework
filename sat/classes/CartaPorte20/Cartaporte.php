<?php

namespace SAT\Generated\CartaPorte20;

class Cartaporte extends \XMLS
{
    public $tagName = 'CartaPorte';
    public $attributes = array (
  0 => 'Version',
  1 => 'TranspInternac',
  2 => 'EntradaSalidaMerc',
  3 => 'PaisOrigenDestino',
  4 => 'ViaEntradaSalida',
  5 => 'TotalDistRec',
);
    public $_sequence = array (
  0 => 'Ubicaciones',
  1 => 'Mercancias',
  2 => 'FiguraTransporte',
);

    public $Version = '';
    public $TranspInternac = '';
    public $EntradaSalidaMerc = '';
    public $PaisOrigenDestino = '';
    public $ViaEntradaSalida = '';
    public $TotalDistRec = '';
    public $Ubicaciones = '';
    public $Mercancias = '';
    public $FiguraTransporte = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
