<?php

namespace SAT\Generated\spei;

class ComplementoSpeiSpeiTerceroOrdenante extends \XMLS
{
    public $tagName = 'Ordenante';
    public $attributes = array (
  0 => 'BancoEmisor',
  1 => 'Nombre',
  2 => 'TipoCuenta',
  3 => 'Cuenta',
  4 => 'RFC',
);
    public $_sequence = array (
);

    public $BancoEmisor = '';
    public $Nombre = '';
    public $TipoCuenta = '';
    public $Cuenta = '';
    public $RFC = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
