<?php

namespace SAT\Generated\detallista;

class DetallistaPaymentterms extends \XMLS
{
    public $tagName = 'paymentTerms';
    public $attributes = array (
  0 => 'paymentTermsEvent',
  1 => 'PaymentTermsRelationTime',
);
    public $_sequence = array (
  0 => 'netPayment',
  1 => 'discountPayment',
);

    public $paymentTermsEvent = '';
    public $PaymentTermsRelationTime = '';
    public $netPayment = '';
    public $discountPayment = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
