<?php

namespace SAT\Generated\GastosHidrocarburos10;

class GastoshidrocarburosErogacion extends \XMLS
{
    public $tagName = 'Erogacion';
    public $attributes = array (
  0 => 'TipoErogacion',
  1 => 'MontocuErogacion',
  2 => 'Porcentaje',
);
    public $_sequence = array (
  0 => 'DocumentoRelacionado',
  1 => 'Actividades',
  2 => 'CentroCostos',
);

    public $TipoErogacion = '';
    public $MontocuErogacion = '';
    public $Porcentaje = '';
    public $DocumentoRelacionado = [];
    public $Actividades = [];
    public $CentroCostos = [];

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
