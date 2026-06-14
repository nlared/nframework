<?php

namespace SAT\Generated\Pagos20;

class PagosTotales extends \XMLS
{
    public $tagName = 'Totales';
    public $attributes = array (
  0 => 'TotalRetencionesIVA',
  1 => 'TotalRetencionesISR',
  2 => 'TotalRetencionesIEPS',
  3 => 'TotalTrasladosBaseIVA16',
  4 => 'TotalTrasladosImpuestoIVA16',
  5 => 'TotalTrasladosBaseIVA8',
  6 => 'TotalTrasladosImpuestoIVA8',
  7 => 'TotalTrasladosBaseIVA0',
  8 => 'TotalTrasladosImpuestoIVA0',
  9 => 'TotalTrasladosBaseIVAExento',
  10 => 'MontoTotalPagos',
);
    public $_sequence = array (
);

    public $TotalRetencionesIVA = '';
    public $TotalRetencionesISR = '';
    public $TotalRetencionesIEPS = '';
    public $TotalTrasladosBaseIVA16 = '';
    public $TotalTrasladosImpuestoIVA16 = '';
    public $TotalTrasladosBaseIVA8 = '';
    public $TotalTrasladosImpuestoIVA8 = '';
    public $TotalTrasladosBaseIVA0 = '';
    public $TotalTrasladosImpuestoIVA0 = '';
    public $TotalTrasladosBaseIVAExento = '';
    public $MontoTotalPagos = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
