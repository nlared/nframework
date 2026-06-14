<?php

namespace SAT\Generated\donat11;

class Donatarias extends \XMLS
{
    public $tagName = 'Donatarias';
    public $attributes = array (
  0 => 'version',
  1 => 'noAutorizacion',
  2 => 'fechaAutorizacion',
  3 => 'leyenda',
);
    public $_sequence = array (
);

    public $version = '';
    public $noAutorizacion = '';
    public $fechaAutorizacion = '';
    public $leyenda = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
