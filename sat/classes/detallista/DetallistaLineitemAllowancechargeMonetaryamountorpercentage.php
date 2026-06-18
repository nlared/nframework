<?php

namespace SAT\Generated\detallista;

class DetallistaLineitemAllowancechargeMonetaryamountorpercentage extends \XMLS
{
    public $tagName = 'monetaryAmountOrPercentage';
    public $attributes = array (
);
    public $_sequence = array (
  0 => 'percentagePerUnit',
  1 => 'ratePerUnit',
);

    public $percentagePerUnit = '';
    public $ratePerUnit = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
