<?php

namespace SAT\Generated\ecc11;

class EstadodecuentacombustibleConceptosConceptoestadodecuentacombustible extends \XMLS
{
    public $tagName = 'ConceptoEstadoDeCuentaCombustible';
    public $attributes = array (
  0 => 'Identificador',
  1 => 'Fecha',
  2 => 'Rfc',
  3 => 'ClaveEstacion',
  4 => 'TAR',
  5 => 'Cantidad',
  6 => 'NoIdentificacion',
  7 => 'Unidad',
  8 => 'NombreCombustible',
  9 => 'FolioOperacion',
  10 => 'ValorUnitario',
  11 => 'Importe',
);
    public $_sequence = array (
  0 => 'Traslados',
);

    public $Identificador = '';
    public $Fecha = '';
    public $Rfc = '';
    public $ClaveEstacion = '';
    public $TAR = '';
    public $Cantidad = '';
    public $NoIdentificacion = '';
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
