<?php

namespace SAT\Generated\certificadodedestruccion;

class CertificadodedestruccionVehiculodestruido extends \XMLS
{
    public $tagName = 'VehiculoDestruido';
    public $attributes = array (
  0 => 'Marca',
  1 => 'TipooClase',
  2 => 'A__o',
  3 => 'Modelo',
  4 => 'NIV',
  5 => 'NumSerie',
  6 => 'NumPlacas',
  7 => 'NumMotor',
  8 => 'NumFolTarjCir',
);
    public $_sequence = array (
);

    public $Marca = '';
    public $TipooClase = '';
    public $A__o = '';
    public $Modelo = '';
    public $NIV = '';
    public $NumSerie = '';
    public $NumPlacas = '';
    public $NumMotor = '';
    public $NumFolTarjCir = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
