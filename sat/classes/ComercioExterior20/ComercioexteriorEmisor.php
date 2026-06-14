<?php

namespace SAT\Generated\ComercioExterior20;

class ComercioexteriorEmisor extends \XMLS
{
    public $tagName = 'Emisor';
    public $attributes = array (
  0 => 'Curp',
);
    public $_sequence = array (
  0 => 'Domicilio',
);

    public $Curp = '';
    public $Domicilio = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
