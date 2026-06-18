<?php

namespace SAT\Generated\dividendos;

class Dividendos extends \XMLS
{
    public $tagName = 'Dividendos';
    public $attributes = array (
  0 => 'Version',
);
    public $_sequence = array (
  0 => 'DividOUtil',
  1 => 'Remanente',
);

    public $Version = '';
    public $DividOUtil = '';
    public $Remanente = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
