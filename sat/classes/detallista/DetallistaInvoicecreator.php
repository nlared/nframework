<?php

namespace SAT\Generated\detallista;

class DetallistaInvoicecreator extends \XMLS
{
    public $tagName = 'InvoiceCreator';
    public $attributes = array (
);
    public $_sequence = array (
  0 => 'gln',
  1 => 'alternatePartyIdentification',
  2 => 'nameAndAddress',
);

    public $gln = '';
    public $alternatePartyIdentification = '';
    public $nameAndAddress = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
