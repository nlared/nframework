<?php

namespace SAT\Generated\servicioparcialconstruccion;

class Parcialesconstruccion extends \XMLS
{
    public $tagName = 'parcialesconstruccion';
    public $attributes = array (
  0 => 'Version',
  1 => 'NumPerLicoAut',
);
    public $_sequence = array (
  0 => 'Inmueble',
);

    public $Version = '';
    public $NumPerLicoAut = '';
    public $Inmueble = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
