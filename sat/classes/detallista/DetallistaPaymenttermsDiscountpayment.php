<?php

namespace SAT\Generated\detallista;

class DetallistaPaymenttermsDiscountpayment extends \XMLS
{
    public $tagName = 'discountPayment';
    public $attributes = array (
  0 => 'discountType',
);
    public $_sequence = array (
  0 => 'percentage',
);

    public $discountType = '';
    public $percentage = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
