<?php

namespace SAT\Generated\Pagos10;

class Pagos extends \XMLS
{
    public $tagName = 'Pagos';
    public $attributes = array (
  0 => 'Version',
);
    public $_sequence = array (
  0 => 'Pago',
);

    public $Version = '';
    public $Pago = [];

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
