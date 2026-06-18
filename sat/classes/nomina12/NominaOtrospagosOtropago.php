<?php

namespace SAT\Generated\nomina12;

class NominaOtrospagosOtropago extends \XMLS
{
    public $tagName = 'OtroPago';
    public $attributes = array (
  0 => 'TipoOtroPago',
  1 => 'Clave',
  2 => 'Concepto',
  3 => 'Importe',
);
    public $_sequence = array (
  0 => 'SubsidioAlEmpleo',
  1 => 'CompensacionSaldosAFavor',
);

    public $TipoOtroPago = '';
    public $Clave = '';
    public $Concepto = '';
    public $Importe = '';
    public $SubsidioAlEmpleo = '';
    public $CompensacionSaldosAFavor = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
