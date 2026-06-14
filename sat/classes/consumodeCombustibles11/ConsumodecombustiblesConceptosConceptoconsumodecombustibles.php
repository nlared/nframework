<?php

namespace SAT\Generated\consumodeCombustibles11;

class ConsumodecombustiblesConceptosConceptoconsumodecombustibles extends \XMLS
{
    public $tagName = 'ConceptoConsumoDeCombustibles';
    public $attributes = array (
  0 => 'identificador',
  1 => 'fecha',
  2 => 'rfc',
  3 => 'claveEstacion',
  4 => 'tipoCombustible',
  5 => 'cantidad',
  6 => 'nombreCombustible',
  7 => 'folioOperacion',
  8 => 'valorUnitario',
  9 => 'importe',
);
    public $_sequence = array (
  0 => 'Determinados',
);

    public $identificador = '';
    public $fecha = '';
    public $rfc = '';
    public $claveEstacion = '';
    public $tipoCombustible = '';
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
