<?php

namespace SAT\Generated\spei;

class ComplementoSpeiSpeiTerceroBeneficiario extends \XMLS
{
    public $tagName = 'Beneficiario';
    public $attributes = array (
  0 => 'BancoReceptor',
  1 => 'Nombre',
  2 => 'TipoCuenta',
  3 => 'Cuenta',
  4 => 'RFC',
  5 => 'Concepto',
  6 => 'IVA',
  7 => 'MontoPago',
);
    public $_sequence = array (
);

    public $BancoReceptor = '';
    public $Nombre = '';
    public $TipoCuenta = '';
    public $Cuenta = '';
    public $RFC = '';
    public $Concepto = '';
    public $IVA = '';
    public $MontoPago = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
