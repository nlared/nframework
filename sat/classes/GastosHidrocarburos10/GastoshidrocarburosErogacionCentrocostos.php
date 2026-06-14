<?php

namespace SAT\Generated\GastosHidrocarburos10;

class GastoshidrocarburosErogacionCentrocostos extends \XMLS
{
    public $tagName = 'CentroCostos';
    public $attributes = array (
  0 => 'Campo',
);
    public $_sequence = array (
  0 => 'Yacimientos',
);

    public $Campo = '';
    public $Yacimientos = [];

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
