<?php

namespace SAT\Generated\detallista;

class DetallistaLineitemAllowancecharge extends \XMLS
{
    public $tagName = 'allowanceCharge';
    public $attributes = array (
  0 => 'allowanceChargeType',
  1 => 'settlementType',
  2 => 'sequenceNumber',
);
    public $_sequence = array (
  0 => 'specialServicesType',
  1 => 'monetaryAmountOrPercentage',
);

    public $allowanceChargeType = '';
    public $settlementType = '';
    public $sequenceNumber = '';
    public $specialServicesType = '';
    public $monetaryAmountOrPercentage = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
