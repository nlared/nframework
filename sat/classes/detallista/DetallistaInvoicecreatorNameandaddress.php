<?php

namespace SAT\Generated\detallista;

class DetallistaInvoicecreatorNameandaddress extends \XMLS
{
    public $tagName = 'nameAndAddress';
    public $attributes = array (
);
    public $_sequence = array (
  0 => 'name',
  1 => 'streetAddressOne',
  2 => 'city',
  3 => 'postalCode',
);

    public $name = '';
    public $streetAddressOne = '';
    public $city = '';
    public $postalCode = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
