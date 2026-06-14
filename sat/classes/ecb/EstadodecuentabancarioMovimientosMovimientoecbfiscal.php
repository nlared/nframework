<?php

namespace SAT\Generated\ecb;

class EstadodecuentabancarioMovimientosMovimientoecbfiscal extends \XMLS
{
    public $tagName = 'MovimientoECBFiscal';
    public $attributes = array (
  0 => 'fecha',
  1 => 'referencia',
  2 => 'descripcion',
  3 => 'RFCenajenante',
  4 => 'Importe',
  5 => 'moneda',
  6 => 'saldoInicial',
  7 => 'saldoAlCorte',
);
    public $_sequence = array (
);

    public $fecha = '';
    public $referencia = '';
    public $descripcion = '';
    public $RFCenajenante = '';
    public $Importe = '';
    public $moneda = '';
    public $saldoInicial = '';
    public $saldoAlCorte = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
