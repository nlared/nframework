<?php

namespace SAT\Generated\notariospublicos;

class NotariospublicosDatosnotario extends \XMLS
{
    public $tagName = 'DatosNotario';
    public $attributes = array (
  0 => 'CURP',
  1 => 'NumNotaria',
  2 => 'EntidadFederativa',
  3 => 'Adscripcion',
);
    public $_sequence = array (
);

    public $CURP = '';
    public $NumNotaria = '';
    public $EntidadFederativa = '';
    public $Adscripcion = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
