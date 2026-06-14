<?php

namespace SAT\Generated\fideicomisonoempresarial;

class FideicomisonoempresarialDeduccosalidas extends \XMLS
{
    public $tagName = 'DeduccOSalidas';
    public $attributes = array (
  0 => 'MontTotEgresPeriodo',
  1 => 'PartPropDelFideicom',
  2 => 'PropDelMontTot',
);
    public $_sequence = array (
  0 => 'IntegracEgresos',
);

    public $MontTotEgresPeriodo = '';
    public $PartPropDelFideicom = '';
    public $PropDelMontTot = '';
    public $IntegracEgresos = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
