<?php

namespace SAT\Generated\GastosHidrocarburos10;

class GastoshidrocarburosErogacionCentrocostosYacimientos extends \XMLS
{
    public $tagName = 'Yacimientos';
    public $attributes = array (
  0 => 'Yacimiento',
);
    public $_sequence = array (
  0 => 'Pozos',
);

    public $Yacimiento = '';
    public $Pozos = [];

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
