<?php

namespace SAT\Generated\ecb;

class EstadodecuentabancarioMovimientosMovimientoecb extends \XMLS
{
    public $tagName = 'MovimientoECB';
    public $attributes = array (
  0 => 'fecha',
  1 => 'referencia',
  2 => 'descripcion',
  3 => 'importe',
  4 => 'moneda',
  5 => 'saldoInicial',
  6 => 'saldoAlCorte',
);
    public $_sequence = array (
);

    public $fecha = '';
    public $referencia = '';
    public $descripcion = '';
    public $importe = '';
    public $moneda = '';
    public $saldoInicial = '';
    public $saldoAlCorte = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
