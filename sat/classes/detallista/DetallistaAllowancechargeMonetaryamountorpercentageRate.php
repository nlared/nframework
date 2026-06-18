<?php

namespace SAT\Generated\detallista;

class DetallistaAllowancechargeMonetaryamountorpercentageRate extends \XMLS
{
    public $tagName = 'rate';
    public $attributes = array (
  0 => 'base',
);
    public $_sequence = array (
  0 => 'percentage',
);

    public $base = '';
    public $percentage = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
