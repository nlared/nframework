<?php

namespace SAT\Generated\detallista;

class DetallistaTotalallowancecharge extends \XMLS
{
    public $tagName = 'TotalAllowanceCharge';
    public $attributes = array (
  0 => 'allowanceOrChargeType',
);
    public $_sequence = array (
  0 => 'specialServicesType',
  1 => 'Amount',
);

    public $allowanceOrChargeType = '';
    public $specialServicesType = '';
    public $Amount = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
