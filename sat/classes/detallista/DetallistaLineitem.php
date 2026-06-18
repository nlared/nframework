<?php

namespace SAT\Generated\detallista;

class DetallistaLineitem extends \XMLS
{
    public $tagName = 'lineItem';
    public $attributes = array (
  0 => 'type',
  1 => 'number',
);
    public $_sequence = array (
  0 => 'tradeItemIdentification',
  1 => 'alternateTradeItemIdentification',
  2 => 'tradeItemDescriptionInformation',
  3 => 'invoicedQuantity',
  4 => 'aditionalQuantity',
  5 => 'grossPrice',
  6 => 'netPrice',
  7 => 'AdditionalInformation',
  8 => 'Customs',
  9 => 'LogisticUnits',
  10 => 'palletInformation',
  11 => 'extendedAttributes',
  12 => 'allowanceCharge',
  13 => 'tradeItemTaxInformation',
  14 => 'totalLineAmount',
);

    public $type = '';
    public $number = '';
    public $tradeItemIdentification = '';
    public $alternateTradeItemIdentification = [];
    public $tradeItemDescriptionInformation = '';
    public $invoicedQuantity = '';
    public $aditionalQuantity = [];
    public $grossPrice = '';
    public $netPrice = '';
    public $AdditionalInformation = '';
    public $Customs = [];
    public $LogisticUnits = '';
    public $palletInformation = '';
    public $extendedAttributes = '';
    public $allowanceCharge = [];
    public $tradeItemTaxInformation = [];
    public $totalLineAmount = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
