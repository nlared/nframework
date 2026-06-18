<?php

namespace SAT\Generated\detallista;

class DetallistaBuyer extends \XMLS
{
    public $tagName = 'buyer';
    public $attributes = array (
);
    public $_sequence = array (
  0 => 'gln',
  1 => 'contactInformation',
);

    public $gln = '';
    public $contactInformation = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
