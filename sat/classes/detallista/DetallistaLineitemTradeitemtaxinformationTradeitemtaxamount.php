<?php

namespace SAT\Generated\detallista;

class DetallistaLineitemTradeitemtaxinformationTradeitemtaxamount extends \XMLS
{
    public $tagName = 'tradeItemTaxAmount';
    public $attributes = array (
);
    public $_sequence = array (
  0 => 'taxPercentage',
  1 => 'taxAmount',
);

    public $taxPercentage = '';
    public $taxAmount = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
