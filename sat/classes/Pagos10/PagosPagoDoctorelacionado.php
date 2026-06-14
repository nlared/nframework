<?php

namespace SAT\Generated\Pagos10;

class PagosPagoDoctorelacionado extends \XMLS
{
    public $tagName = 'DoctoRelacionado';
    public $attributes = array (
  0 => 'IdDocumento',
  1 => 'Serie',
  2 => 'Folio',
  3 => 'MonedaDR',
  4 => 'TipoCambioDR',
  5 => 'MetodoDePagoDR',
  6 => 'NumParcialidad',
  7 => 'ImpSaldoAnt',
  8 => 'ImpPagado',
  9 => 'ImpSaldoInsoluto',
);
    public $_sequence = array (
);

    public $IdDocumento = '';
    public $Serie = '';
    public $Folio = '';
    public $MonedaDR = '';
    public $TipoCambioDR = '';
    public $MetodoDePagoDR = '';
    public $NumParcialidad = '';
    public $ImpSaldoAnt = '';
    public $ImpPagado = '';
    public $ImpSaldoInsoluto = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
