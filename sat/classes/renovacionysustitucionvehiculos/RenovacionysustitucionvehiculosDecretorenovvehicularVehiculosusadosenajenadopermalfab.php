<?php

namespace SAT\Generated\renovacionysustitucionvehiculos;

class RenovacionysustitucionvehiculosDecretorenovvehicularVehiculosusadosenajenadopermalfab extends \XMLS
{
    public $tagName = 'VehiculosUsadosEnajenadoPermAlFab';
    public $attributes = array (
  0 => 'PrecioVehUsado',
  1 => 'TipoVeh',
  2 => 'Marca',
  3 => 'TipooClase',
  4 => 'A__o',
  5 => 'Modelo',
  6 => 'NIV',
  7 => 'NumSerie',
  8 => 'NumPlacas',
  9 => 'NumMotor',
  10 => 'NumFolTarjCir',
  11 => 'NumPedIm',
  12 => 'Aduana',
  13 => 'FechaRegulVeh',
  14 => 'Foliofiscal',
);
    public $_sequence = array (
);

    public $PrecioVehUsado = '';
    public $TipoVeh = '';
    public $Marca = '';
    public $TipooClase = '';
    public $A__o = '';
    public $Modelo = '';
    public $NIV = '';
    public $NumSerie = '';
    public $NumPlacas = '';
    public $NumMotor = '';
    public $NumFolTarjCir = '';
    public $NumPedIm = '';
    public $Aduana = '';
    public $FechaRegulVeh = '';
    public $Foliofiscal = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
