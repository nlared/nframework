<?php

namespace SAT\Generated\detallista;

class DetallistaOrderidentification extends \XMLS
{
    public $tagName = 'orderIdentification';
    public $attributes = array (
);
    public $_sequence = array (
  0 => 'referenceIdentification',
  1 => 'ReferenceDate',
);

    public $referenceIdentification = [];
    public $ReferenceDate = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
