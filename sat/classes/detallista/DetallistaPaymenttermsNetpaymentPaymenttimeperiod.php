<?php

namespace SAT\Generated\detallista;

class DetallistaPaymenttermsNetpaymentPaymenttimeperiod extends \XMLS
{
    public $tagName = 'paymentTimePeriod';
    public $attributes = array (
);
    public $_sequence = array (
  0 => 'timePeriodDue',
);

    public $timePeriodDue = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
