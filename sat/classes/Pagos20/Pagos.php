<?php

namespace SAT\Generated\Pagos20;

class Pagos extends \XMLS
{
    public $tagName = 'Pagos';
    public $attributes = array (
  0 => 'Version',
);
    public $_sequence = array (
  0 => 'Totales',
  1 => 'Pago',
);

    public $Version = '';
    public $Totales = '';
    public $Pago = [];

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
