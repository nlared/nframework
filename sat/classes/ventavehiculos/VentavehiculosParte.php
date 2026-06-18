<?php

namespace SAT\Generated\ventavehiculos;

class VentavehiculosParte extends \XMLS
{
    public $tagName = 'Parte';
    public $attributes = array (
  0 => 'cantidad',
  1 => 'unidad',
  2 => 'noIdentificacion',
  3 => 'descripcion',
  4 => 'valorUnitario',
  5 => 'importe',
);
    public $_sequence = array (
  0 => 'InformacionAduanera',
);

    public $cantidad = '';
    public $unidad = '';
    public $noIdentificacion = '';
    public $descripcion = '';
    public $valorUnitario = '';
    public $importe = '';
    public $InformacionAduanera = [];

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
