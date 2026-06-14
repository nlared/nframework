<?php

namespace SAT\Generated\aerolineas;

class Aerolineas extends \XMLS
{
    public $tagName = 'Aerolineas';
    public $attributes = array (
  0 => 'Version',
  1 => 'TUA',
);
    public $_sequence = array (
  0 => 'OtrosCargos',
);

    public $Version = '';
    public $TUA = '';
    public $OtrosCargos = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
