<?php

namespace SAT\Generated\fideicomisonoempresarial;

class FideicomisonoempresarialIngresosoentradas extends \XMLS
{
    public $tagName = 'IngresosOEntradas';
    public $attributes = array (
  0 => 'MontTotEntradasPeriodo',
  1 => 'PartPropAcumDelFideicom',
  2 => 'PropDelMontTot',
);
    public $_sequence = array (
  0 => 'IntegracIngresos',
);

    public $MontTotEntradasPeriodo = '';
    public $PartPropAcumDelFideicom = '';
    public $PropDelMontTot = '';
    public $IntegracIngresos = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
