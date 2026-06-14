<?php

namespace SAT\Generated\AcreditamientoIEPS10;

class Acreditamientoieps extends \XMLS
{
    public $tagName = 'acreditamientoIEPS';
    public $attributes = array (
  0 => 'Version',
  1 => 'TAR',
);
    public $_sequence = array (
);

    public $Version = '';
    public $TAR = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
