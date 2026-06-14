<?php

namespace SAT\Generated\CartaPorte;

class CartaporteUbicacionesUbicacion extends \XMLS
{
    public $tagName = 'Ubicacion';
    public $attributes = array (
  0 => 'TipoEstacion',
  1 => 'DistanciaRecorrida',
);
    public $_sequence = array (
  0 => 'Origen',
  1 => 'Destino',
  2 => 'Domicilio',
);

    public $TipoEstacion = '';
    public $DistanciaRecorrida = '';
    public $Origen = '';
    public $Destino = '';
    public $Domicilio = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
