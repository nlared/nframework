<?php

namespace SAT\Generated\intereseshipotecarios;

class Intereseshipotecarios extends \XMLS
{
    public $tagName = 'Intereseshipotecarios';
    public $attributes = array (
  0 => 'Version',
  1 => 'CreditoDeInstFinanc',
  2 => 'SaldoInsoluto',
  3 => 'PropDeducDelCredit',
  4 => 'MontTotIntNominalesDev',
  5 => 'MontTotIntNominalesDevYPag',
  6 => 'MontTotIntRealPagDeduc',
  7 => 'NumContrato',
);
    public $_sequence = array (
);

    public $Version = '';
    public $CreditoDeInstFinanc = '';
    public $SaldoInsoluto = '';
    public $PropDeducDelCredit = '';
    public $MontTotIntNominalesDev = '';
    public $MontTotIntNominalesDevYPag = '';
    public $MontTotIntRealPagDeduc = '';
    public $NumContrato = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
