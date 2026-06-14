<?php

namespace SAT\Generated\vehiculousado;

class Vehiculousado extends \XMLS
{
    public $tagName = 'VehiculoUsado';
    public $attributes = array (
  0 => 'Version',
  1 => 'montoAdquisicion',
  2 => 'montoEnajenacion',
  3 => 'claveVehicular',
  4 => 'marca',
  5 => 'tipo',
  6 => 'modelo',
  7 => 'numeroMotor',
  8 => 'numeroSerie',
  9 => 'NIV',
  10 => 'valor',
);
    public $_sequence = array (
  0 => 'InformacionAduanera',
);

    public $Version = '';
    public $montoAdquisicion = '';
    public $montoEnajenacion = '';
    public $claveVehicular = '';
    public $marca = '';
    public $tipo = '';
    public $modelo = '';
    public $numeroMotor = '';
    public $numeroSerie = '';
    public $NIV = '';
    public $valor = '';
    public $InformacionAduanera = [];

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
