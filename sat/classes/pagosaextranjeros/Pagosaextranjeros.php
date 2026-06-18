<?php

namespace SAT\Generated\pagosaextranjeros;

class Pagosaextranjeros extends \XMLS
{
    public $tagName = 'Pagosaextranjeros';
    public $attributes = array (
  0 => 'Version',
  1 => 'EsBenefEfectDelCobro',
);
    public $_sequence = array (
  0 => 'NoBeneficiario',
  1 => 'Beneficiario',
);

    public $Version = '';
    public $EsBenefEfectDelCobro = '';
    public $NoBeneficiario = '';
    public $Beneficiario = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
