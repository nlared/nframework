<?php

namespace SAT\Generated\consumodecombustibles;

class ConsumodecombustiblesConceptosConceptoconsumodecombustibles extends \XMLS
{
    public $tagName = 'ConceptoConsumoDeCombustibles';
    public $attributes = array (
  0 => 'identificador',
  1 => 'fecha',
  2 => 'rfc',
  3 => 'claveEstacion',
  4 => 'cantidad',
  5 => 'nombreCombustible',
  6 => 'folioOperacion',
  7 => 'valorUnitario',
  8 => 'importe',
);
    public $_sequence = array (
  0 => 'Determinados',
);

    public $identificador = '';
    public $fecha = '';
    public $rfc = '';
    public $claveEstacion = '';
    public $cantidad = '';
    public $nombreCombustible = '';
    public $folioOperacion = '';
    public $valorUnitario = '';
    public $importe = '';
    public $Determinados = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
