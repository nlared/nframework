<?php

namespace SAT\Generated\ine11;

class IneEntidad extends \XMLS
{
    public $tagName = 'Entidad';
    public $attributes = array (
  0 => 'ClaveEntidad',
  1 => 'Ambito',
);
    public $_sequence = array (
  0 => 'Contabilidad',
);

    public $ClaveEntidad = '';
    public $Ambito = '';
    public $Contabilidad = [];

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
