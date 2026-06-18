<?php

namespace SAT\Generated\aerolineas;

class AerolineasOtroscargosCargo extends \XMLS
{
    public $tagName = 'Cargo';
    public $attributes = array (
  0 => 'CodigoCargo',
  1 => 'Importe',
);
    public $_sequence = array (
);

    public $CodigoCargo = '';
    public $Importe = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
