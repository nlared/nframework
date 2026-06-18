<?php

namespace SAT\Generated\detallista;

class DetallistaRequestforpaymentidentification extends \XMLS
{
    public $tagName = 'requestForPaymentIdentification';
    public $attributes = array (
);
    public $_sequence = array (
  0 => 'entityType',
);

    public $entityType = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
