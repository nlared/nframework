<?php

namespace SAT\Generated\hidrocarburospetroliferos;

class Hidroypetro extends \XMLS
{
    public $tagName = 'HidroYPetro';
    public $attributes = array (
  0 => 'Version',
  1 => 'TipoPermiso',
  2 => 'NumeroPermiso',
  3 => 'ClaveHYP',
  4 => 'SubProductoHYP',
);
    public $_sequence = array (
);

    public $Version = '';
    public $TipoPermiso = '';
    public $NumeroPermiso = '';
    public $ClaveHYP = '';
    public $SubProductoHYP = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
