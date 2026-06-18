<?php

namespace SAT\Generated\cfdv22;

class ComprobanteConceptosConcepto extends \XMLS
{
    public $tagName = 'Concepto';
    public $attributes = array (
  0 => 'cantidad',
  1 => 'unidad',
  2 => 'noIdentificacion',
  3 => 'descripcion',
  4 => 'valorUnitario',
  5 => 'importe',
);
    public $_sequence = array (
);

    public $cantidad = '';
    public $unidad = '';
    public $noIdentificacion = '';
    public $descripcion = '';
    public $valorUnitario = '';
    public $importe = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
