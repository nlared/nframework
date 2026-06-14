<?php

namespace SAT\Generated\cfdiregistrofiscal;

class Cfdiregistrofiscal extends \XMLS
{
    public $tagName = 'CFDIRegistroFiscal';
    public $attributes = array (
  0 => 'Version',
  1 => 'Folio',
);
    public $_sequence = array (
);

    public $Version = '';
    public $Folio = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
