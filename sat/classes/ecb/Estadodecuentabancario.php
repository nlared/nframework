<?php

namespace SAT\Generated\ecb;

class Estadodecuentabancario extends \XMLS
{
    public $tagName = 'EstadoDeCuentaBancario';
    public $attributes = array (
  0 => 'version',
  1 => 'numeroCuenta',
  2 => 'nombreCliente',
  3 => 'periodo',
  4 => 'sucursal',
);
    public $_sequence = array (
  0 => 'Movimientos',
);

    public $version = '';
    public $numeroCuenta = '';
    public $nombreCliente = '';
    public $periodo = '';
    public $sucursal = '';
    public $Movimientos = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
