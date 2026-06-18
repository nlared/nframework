<?php

namespace SAT\Generated\detallista;

class Detallista extends \XMLS
{
    public $tagName = 'detallista';
    public $attributes = array (
  0 => 'type',
  1 => 'contentVersion',
  2 => 'documentStructureVersion',
  3 => 'documentStatus',
);
    public $_sequence = array (
  0 => 'requestForPaymentIdentification',
  1 => 'specialInstruction',
  2 => 'orderIdentification',
  3 => 'AdditionalInformation',
  4 => 'DeliveryNote',
  5 => 'buyer',
  6 => 'seller',
  7 => 'shipTo',
  8 => 'InvoiceCreator',
  9 => 'Customs',
  10 => 'currency',
  11 => 'paymentTerms',
  12 => 'shipmentDetail',
  13 => 'allowanceCharge',
  14 => 'lineItem',
  15 => 'totalAmount',
  16 => 'TotalAllowanceCharge',
);

    public $type = '';
    public $contentVersion = '';
    public $documentStructureVersion = '';
    public $documentStatus = '';
    public $requestForPaymentIdentification = '';
    public $specialInstruction = [];
    public $orderIdentification = '';
    public $AdditionalInformation = '';
    public $DeliveryNote = '';
    public $buyer = '';
    public $seller = '';
    public $shipTo = '';
    public $InvoiceCreator = '';
    public $Customs = [];
    public $currency = [];
    public $paymentTerms = '';
    public $shipmentDetail = '';
    public $allowanceCharge = [];
    public $lineItem = [];
    public $totalAmount = '';
    public $TotalAllowanceCharge = [];

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
