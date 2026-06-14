<?php

namespace SAT\Generated\detallista;

class DetallistaPaymenttermsNetpayment extends \XMLS
{
    public $tagName = 'netPayment';
    public $attributes = array (
  0 => 'netPaymentTermsType',
);
    public $_sequence = array (
  0 => 'paymentTimePeriod',
);

    public $netPaymentTermsType = '';
    public $paymentTimePeriod = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
