<?php

namespace SAT\Generated\CartaPorte20;

class CartaporteUbicacionesUbicacion extends \XMLS
{
    public $tagName = 'Ubicacion';
    public $attributes = array (
  0 => 'TipoUbicacion',
  1 => 'IDUbicacion',
  2 => 'RFCRemitenteDestinatario',
  3 => 'NombreRemitenteDestinatario',
  4 => 'NumRegIdTrib',
  5 => 'ResidenciaFiscal',
  6 => 'NumEstacion',
  7 => 'NombreEstacion',
  8 => 'NavegacionTrafico',
  9 => 'FechaHoraSalidaLlegada',
  10 => 'TipoEstacion',
  11 => 'DistanciaRecorrida',
);
    public $_sequence = array (
  0 => 'Domicilio',
);

    public $TipoUbicacion = '';
    public $IDUbicacion = '';
    public $RFCRemitenteDestinatario = '';
    public $NombreRemitenteDestinatario = '';
    public $NumRegIdTrib = '';
    public $ResidenciaFiscal = '';
    public $NumEstacion = '';
    public $NombreEstacion = '';
    public $NavegacionTrafico = '';
    public $FechaHoraSalidaLlegada = '';
    public $TipoEstacion = '';
    public $DistanciaRecorrida = '';
    public $Domicilio = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
