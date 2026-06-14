<?php

namespace SAT\Generated\renovacionysustitucionvehiculos;

class RenovacionysustitucionvehiculosDecretosustitvehicularVehiculousadoenajenadopermalfab extends \XMLS
{
    public $tagName = 'VehiculoUsadoEnajenadoPermAlFab';
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
  11 => 'NumFolAvisoint',
  12 => 'NumPedIm',
  13 => 'Aduana',
  14 => 'FechaRegulVeh',
  15 => 'Foliofiscal',
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
    public $NumFolAvisoint = '';
    public $NumPedIm = '';
    public $Aduana = '';
    public $FechaRegulVeh = '';
    public $Foliofiscal = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
