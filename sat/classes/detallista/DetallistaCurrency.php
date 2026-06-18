<?php

namespace SAT\Generated\detallista;

class DetallistaCurrency extends \XMLS
{
    public $tagName = 'currency';
    public $attributes = array (
  0 => 'currencyISOCode',
);
    public $_sequence = array (
  0 => 'currencyFunction',
  1 => 'rateOfChange',
);

    public $currencyISOCode = '';
    public $currencyFunction = [];
    public $rateOfChange = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
