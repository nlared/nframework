<?php

namespace SAT\Generated\ComercioExterior10;

class ComercioexteriorReceptor extends \XMLS
{
    public $tagName = 'Receptor';
    public $attributes = array (
  0 => 'Curp',
  1 => 'NumRegIdTrib',
);
    public $_sequence = array (
);

    public $Curp = '';
    public $NumRegIdTrib = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
