<?php

namespace SAT\Generated\cfdv1;

class Concepto extends \XMLS
{
    public $tagName = 'Concepto';
    public $attributes = array (
  0 => 'cantidad',
  1 => 'unidad',
  2 => 'descripcion',
  3 => 'valorUnitario',
  4 => 'importe',
);
    public $_sequence = array (
);

    public $cantidad = '';
    public $unidad = '';
    public $descripcion = '';
    public $valorUnitario = '';
    public $importe = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
