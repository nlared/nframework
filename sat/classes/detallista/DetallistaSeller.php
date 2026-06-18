<?php

namespace SAT\Generated\detallista;

class DetallistaSeller extends \XMLS
{
    public $tagName = 'seller';
    public $attributes = array (
);
    public $_sequence = array (
  0 => 'gln',
  1 => 'alternatePartyIdentification',
);

    public $gln = '';
    public $alternatePartyIdentification = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
