<?php

namespace SAT\Generated\Pagos10;

class PagosPago extends \XMLS
{
    public $tagName = 'Pago';
    public $attributes = array (
  0 => 'FechaPago',
  1 => 'FormaDePagoP',
  2 => 'MonedaP',
  3 => 'TipoCambioP',
  4 => 'Monto',
  5 => 'NumOperacion',
  6 => 'RfcEmisorCtaOrd',
  7 => 'NomBancoOrdExt',
  8 => 'CtaOrdenante',
  9 => 'RfcEmisorCtaBen',
  10 => 'CtaBeneficiario',
  11 => 'TipoCadPago',
  12 => 'CertPago',
  13 => 'CadPago',
  14 => 'SelloPago',
);
    public $_sequence = array (
  0 => 'DoctoRelacionado',
  1 => 'Impuestos',
);

    public $FechaPago = '';
    public $FormaDePagoP = '';
    public $MonedaP = '';
    public $TipoCambioP = '';
    public $Monto = '';
    public $NumOperacion = '';
    public $RfcEmisorCtaOrd = '';
    public $NomBancoOrdExt = '';
    public $CtaOrdenante = '';
    public $RfcEmisorCtaBen = '';
    public $CtaBeneficiario = '';
    public $TipoCadPago = '';
    public $CertPago = '';
    public $CadPago = '';
    public $SelloPago = '';
    public $DoctoRelacionado = [];
    public $Impuestos = [];

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
