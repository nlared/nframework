<?php

namespace SAT\Generated\planesderetiro11;

class Planesderetiro extends \XMLS
{
    public $tagName = 'Planesderetiro';
    public $attributes = array (
  0 => 'Version',
  1 => 'SistemaFinanc',
  2 => 'MontTotAportAnioInmAnterior',
  3 => 'MontIntRealesDevengAniooInmAnt',
  4 => 'HuboRetirosAnioInmAntPer',
  5 => 'MontTotRetiradoAnioInmAntPer',
  6 => 'MontTotExentRetiradoAnioInmAnt',
  7 => 'MontTotExedenteAnioInmAnt',
  8 => 'HuboRetirosAnioInmAnt',
  9 => 'MontTotRetiradoAnioInmAnt',
  10 => 'NumReferencia',
);
    public $_sequence = array (
  0 => 'AportacionesODepositos',
);

    public $Version = '';
    public $SistemaFinanc = '';
    public $MontTotAportAnioInmAnterior = '';
    public $MontIntRealesDevengAniooInmAnt = '';
    public $HuboRetirosAnioInmAntPer = '';
    public $MontTotRetiradoAnioInmAntPer = '';
    public $MontTotExentRetiradoAnioInmAnt = '';
    public $MontTotExedenteAnioInmAnt = '';
    public $HuboRetirosAnioInmAnt = '';
    public $MontTotRetiradoAnioInmAnt = '';
    public $NumReferencia = '';
    public $AportacionesODepositos = [];

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
