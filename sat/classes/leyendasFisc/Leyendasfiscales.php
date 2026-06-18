<?php

namespace SAT\Generated\leyendasFisc;

class Leyendasfiscales extends \XMLS
{
    public $tagName = 'LeyendasFiscales';
    public $attributes = array (
  0 => 'version',
);
    public $_sequence = array (
  0 => 'Leyenda',
);

    public $version = '';
    public $Leyenda = [];

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
