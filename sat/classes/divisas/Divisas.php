<?php

namespace SAT\Generated\divisas;

class Divisas extends \XMLS
{
    public $tagName = 'Divisas';
    public $attributes = array (
  0 => 'version',
  1 => 'tipoOperacion',
);
    public $_sequence = array (
);

    public $version = '';
    public $tipoOperacion = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
