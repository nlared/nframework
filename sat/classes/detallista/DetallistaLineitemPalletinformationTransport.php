<?php

namespace SAT\Generated\detallista;

class DetallistaLineitemPalletinformationTransport extends \XMLS
{
    public $tagName = 'transport';
    public $attributes = array (
);
    public $_sequence = array (
  0 => 'methodOfPayment',
);

    public $methodOfPayment = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
