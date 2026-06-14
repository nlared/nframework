<?php

namespace SAT\Generated\CartaPorte;

class CartaporteUbicacionesUbicacionOrigen extends \XMLS
{
    public $tagName = 'Origen';
    public $attributes = array (
  0 => 'IDOrigen',
  1 => 'RFCRemitente',
  2 => 'NombreRemitente',
  3 => 'NumRegIdTrib',
  4 => 'ResidenciaFiscal',
  5 => 'NumEstacion',
  6 => 'NombreEstacion',
  7 => 'NavegacionTrafico',
  8 => 'FechaHoraSalida',
);
    public $_sequence = array (
);

    public $IDOrigen = '';
    public $RFCRemitente = '';
    public $NombreRemitente = '';
    public $NumRegIdTrib = '';
    public $ResidenciaFiscal = '';
    public $NumEstacion = '';
    public $NombreEstacion = '';
    public $NavegacionTrafico = '';
    public $FechaHoraSalida = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
