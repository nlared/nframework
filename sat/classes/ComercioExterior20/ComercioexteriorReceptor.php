<?php

namespace SAT\Generated\ComercioExterior20;

class ComercioexteriorReceptor extends \XMLS
{
    public $tagName = 'Receptor';
    public $attributes = array (
  0 => 'NumRegIdTrib',
);
    public $_sequence = array (
  0 => 'Domicilio',
);

    public $NumRegIdTrib = '';
    public $Domicilio = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
