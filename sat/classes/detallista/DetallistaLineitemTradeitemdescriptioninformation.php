<?php

namespace SAT\Generated\detallista;

class DetallistaLineitemTradeitemdescriptioninformation extends \XMLS
{
    public $tagName = 'tradeItemDescriptionInformation';
    public $attributes = array (
  0 => 'language',
);
    public $_sequence = array (
  0 => 'longText',
);

    public $language = '';
    public $longText = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
