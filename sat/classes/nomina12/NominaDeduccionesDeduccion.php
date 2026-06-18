<?php

namespace SAT\Generated\nomina12;

class NominaDeduccionesDeduccion extends \XMLS
{
    public $tagName = 'Deduccion';
    public $attributes = array (
  0 => 'TipoDeduccion',
  1 => 'Clave',
  2 => 'Concepto',
  3 => 'Importe',
);
    public $_sequence = array (
);

    public $TipoDeduccion = '';
    public $Clave = '';
    public $Concepto = '';
    public $Importe = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
