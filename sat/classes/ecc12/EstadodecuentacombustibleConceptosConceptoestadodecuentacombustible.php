<?php

namespace SAT\Generated\ecc12;

class EstadodecuentacombustibleConceptosConceptoestadodecuentacombustible extends \XMLS
{
    public $tagName = 'ConceptoEstadoDeCuentaCombustible';
    public $attributes = array (
  0 => 'Identificador',
  1 => 'Fecha',
  2 => 'Rfc',
  3 => 'ClaveEstacion',
  4 => 'Cantidad',
  5 => 'TipoCombustible',
  6 => 'Unidad',
  7 => 'NombreCombustible',
  8 => 'FolioOperacion',
  9 => 'ValorUnitario',
  10 => 'Importe',
);
    public $_sequence = array (
  0 => 'Traslados',
);

    public $Identificador = '';
    public $Fecha = '';
    public $Rfc = '';
    public $ClaveEstacion = '';
    public $Cantidad = '';
    public $TipoCombustible = '';
    public $Unidad = '';
    public $NombreCombustible = '';
    public $FolioOperacion = '';
    public $ValorUnitario = '';
    public $Importe = '';
    public $Traslados = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
