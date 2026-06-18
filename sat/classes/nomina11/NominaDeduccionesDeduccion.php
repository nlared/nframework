<?php

namespace SAT\Generated\nomina11;

class NominaDeduccionesDeduccion extends \XMLS
{
    public $tagName = 'Deduccion';
    public $attributes = array (
  0 => 'TipoDeduccion',
  1 => 'Clave',
  2 => 'Concepto',
  3 => 'ImporteGravado',
  4 => 'ImporteExento',
);
    public $_sequence = array (
);

    public $TipoDeduccion = '';
    public $Clave = '';
    public $Concepto = '';
    public $ImporteGravado = '';
    public $ImporteExento = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
