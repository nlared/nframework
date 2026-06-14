<?php

namespace SAT\Generated\enajenaciondeacciones;

class Enajenaciondeacciones extends \XMLS
{
    public $tagName = 'EnajenaciondeAcciones';
    public $attributes = array (
  0 => 'Version',
  1 => 'ContratoIntermediacion',
  2 => 'Ganancia',
  3 => 'Perdida',
);
    public $_sequence = array (
);

    public $Version = '';
    public $ContratoIntermediacion = '';
    public $Ganancia = '';
    public $Perdida = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
