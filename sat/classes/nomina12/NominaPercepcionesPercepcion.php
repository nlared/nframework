<?php

namespace SAT\Generated\nomina12;

class NominaPercepcionesPercepcion extends \XMLS
{
    public $tagName = 'Percepcion';
    public $attributes = array (
  0 => 'TipoPercepcion',
  1 => 'Clave',
  2 => 'Concepto',
  3 => 'ImporteGravado',
  4 => 'ImporteExento',
);
    public $_sequence = array (
  0 => 'AccionesOTitulos',
  1 => 'HorasExtra',
);

    public $TipoPercepcion = '';
    public $Clave = '';
    public $Concepto = '';
    public $ImporteGravado = '';
    public $ImporteExento = '';
    public $AccionesOTitulos = '';
    public $HorasExtra = [];

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
