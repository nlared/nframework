<?php

namespace SAT\Generated\arrendamientoenfideicomiso;

class Arrendamientoenfideicomiso extends \XMLS
{
    public $tagName = 'Arrendamientoenfideicomiso';
    public $attributes = array (
  0 => 'Version',
  1 => 'PagProvEfecPorFiduc',
  2 => 'RendimFideicom',
  3 => 'DeduccCorresp',
  4 => 'MontTotRet',
  5 => 'MontResFiscDistFibras',
  6 => 'MontOtrosConceptDistr',
  7 => 'DescrMontOtrosConceptDistr',
);
    public $_sequence = array (
);

    public $Version = '';
    public $PagProvEfecPorFiduc = '';
    public $RendimFideicom = '';
    public $DeduccCorresp = '';
    public $MontTotRet = '';
    public $MontResFiscDistFibras = '';
    public $MontOtrosConceptDistr = '';
    public $DescrMontOtrosConceptDistr = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
