<?php

namespace SAT\Generated\detallista;

class DetallistaLineitemTotallineamount extends \XMLS
{
    public $tagName = 'totalLineAmount';
    public $attributes = array (
);
    public $_sequence = array (
  0 => 'grossAmount',
  1 => 'netAmount',
);

    public $grossAmount = '';
    public $netAmount = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
