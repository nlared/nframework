<?php

namespace SAT\Generated\detallista;

class DetallistaSpecialinstruction extends \XMLS
{
    public $tagName = 'specialInstruction';
    public $attributes = array (
  0 => 'code',
);
    public $_sequence = array (
  0 => 'text',
);

    public $code = '';
    public $text = [];

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
