<?php

namespace SAT\Generated\CartaPorte;

class CartaporteUbicacionesUbicacionDestino extends \XMLS
{
    public $tagName = 'Destino';
    public $attributes = array (
  0 => 'IDDestino',
  1 => 'RFCDestinatario',
  2 => 'NombreDestinatario',
  3 => 'NumRegIdTrib',
  4 => 'ResidenciaFiscal',
  5 => 'NumEstacion',
  6 => 'NombreEstacion',
  7 => 'NavegacionTrafico',
  8 => 'FechaHoraProgLlegada',
);
    public $_sequence = array (
);

    public $IDDestino = '';
    public $RFCDestinatario = '';
    public $NombreDestinatario = '';
    public $NumRegIdTrib = '';
    public $ResidenciaFiscal = '';
    public $NumEstacion = '';
    public $NombreEstacion = '';
    public $NavegacionTrafico = '';
    public $FechaHoraProgLlegada = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
