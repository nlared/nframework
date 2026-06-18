<?php

namespace SAT\Generated\fideicomisonoempresarial;

class FideicomisonoempresarialRetefectfideicomiso extends \XMLS
{
    public $tagName = 'RetEfectFideicomiso';
    public $attributes = array (
  0 => 'MontRetRelPagFideic',
  1 => 'DescRetRelPagFideic',
);
    public $_sequence = array (
);

    public $MontRetRelPagFideic = '';
    public $DescRetRelPagFideic = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
