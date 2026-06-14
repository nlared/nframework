<?php

namespace SAT\Generated\premios;

class Premios extends \XMLS
{
    public $tagName = 'Premios';
    public $attributes = array (
  0 => 'Version',
  1 => 'EntidadFederativa',
  2 => 'MontTotPago',
  3 => 'MontTotPagoGrav',
  4 => 'MontTotPagoExent',
);
    public $_sequence = array (
);

    public $Version = '';
    public $EntidadFederativa = '';
    public $MontTotPago = '';
    public $MontTotPagoGrav = '';
    public $MontTotPagoExent = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
