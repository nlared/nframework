<?php

namespace SAT\Generated\Pagos20;

class PagosPagoDoctorelacionado extends \XMLS
{
    public $tagName = 'DoctoRelacionado';
    public $attributes = array (
  0 => 'IdDocumento',
  1 => 'Serie',
  2 => 'Folio',
  3 => 'MonedaDR',
  4 => 'EquivalenciaDR',
  5 => 'NumParcialidad',
  6 => 'ImpSaldoAnt',
  7 => 'ImpPagado',
  8 => 'ImpSaldoInsoluto',
  9 => 'ObjetoImpDR',
);
    public $_sequence = array (
  0 => 'ImpuestosDR',
);

    public $IdDocumento = '';
    public $Serie = '';
    public $Folio = '';
    public $MonedaDR = '';
    public $EquivalenciaDR = '';
    public $NumParcialidad = '';
    public $ImpSaldoAnt = '';
    public $ImpPagado = '';
    public $ImpSaldoInsoluto = '';
    public $ObjetoImpDR = '';
    public $ImpuestosDR = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
