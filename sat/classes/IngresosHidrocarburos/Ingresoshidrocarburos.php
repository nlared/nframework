<?php

namespace SAT\Generated\IngresosHidrocarburos;

class Ingresoshidrocarburos extends \XMLS
{
    public $tagName = 'IngresosHidrocarburos';
    public $attributes = array (
  0 => 'Version',
  1 => 'NumeroContrato',
  2 => 'ContraprestacionPagadaOperador',
  3 => 'Porcentaje',
);
    public $_sequence = array (
  0 => 'DocumentoRelacionado',
);

    public $Version = '';
    public $NumeroContrato = '';
    public $ContraprestacionPagadaOperador = '';
    public $Porcentaje = '';
    public $DocumentoRelacionado = [];

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
