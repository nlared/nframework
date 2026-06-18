<?php

namespace SAT\Generated\nomina12;

class NominaOtrospagosOtropagoCompensacionsaldosafavor extends \XMLS
{
    public $tagName = 'CompensacionSaldosAFavor';
    public $attributes = array (
  0 => 'SaldoAFavor',
  1 => 'A__o',
  2 => 'RemanenteSalFav',
);
    public $_sequence = array (
);

    public $SaldoAFavor = '';
    public $A__o = '';
    public $RemanenteSalFav = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
