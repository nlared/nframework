<?php

namespace SAT\Generated\aerolineas;

class AerolineasOtroscargos extends \XMLS
{
    public $tagName = 'OtrosCargos';
    public $attributes = array (
  0 => 'TotalCargos',
);
    public $_sequence = array (
  0 => 'Cargo',
);

    public $TotalCargos = '';
    public $Cargo = [];

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
