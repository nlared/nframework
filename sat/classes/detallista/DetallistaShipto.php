<?php

namespace SAT\Generated\detallista;

class DetallistaShipto extends \XMLS
{
    public $tagName = 'shipTo';
    public $attributes = array (
);
    public $_sequence = array (
  0 => 'gln',
  1 => 'nameAndAddress',
);

    public $gln = '';
    public $nameAndAddress = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
