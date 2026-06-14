<?php

namespace SAT\Generated\nomina12;

class NominaEmisorEntidadsncf extends \XMLS
{
    public $tagName = 'EntidadSNCF';
    public $attributes = array (
  0 => 'OrigenRecurso',
  1 => 'MontoRecursoPropio',
);
    public $_sequence = array (
);

    public $OrigenRecurso = '';
    public $MontoRecursoPropio = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
