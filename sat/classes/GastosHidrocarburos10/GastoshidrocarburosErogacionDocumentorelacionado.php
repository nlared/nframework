<?php

namespace SAT\Generated\GastosHidrocarburos10;

class GastoshidrocarburosErogacionDocumentorelacionado extends \XMLS
{
    public $tagName = 'DocumentoRelacionado';
    public $attributes = array (
  0 => 'OrigenErogacion',
  1 => 'FolioFiscalVinculado',
  2 => 'RFCProveedor',
  3 => 'MontoTotalIVA',
  4 => 'MontoRetencionISR',
  5 => 'MontoRetencionIVA',
  6 => 'MontoRetencionOtrosImpuestos',
  7 => 'NumeroPedimentoVinculado',
  8 => 'ClavePedimentoVinculado',
  9 => 'ClavePagoPedimentoVinculado',
  10 => 'MontoIVAPedimento',
  11 => 'OtrosImpuestosPagadosPedimento',
  12 => 'FechaFolioFiscalVinculado',
  13 => 'Mes',
  14 => 'MontoTotalErogaciones',
);
    public $_sequence = array (
);

    public $OrigenErogacion = '';
    public $FolioFiscalVinculado = '';
    public $RFCProveedor = '';
    public $MontoTotalIVA = '';
    public $MontoRetencionISR = '';
    public $MontoRetencionIVA = '';
    public $MontoRetencionOtrosImpuestos = '';
    public $NumeroPedimentoVinculado = '';
    public $ClavePedimentoVinculado = '';
    public $ClavePagoPedimentoVinculado = '';
    public $MontoIVAPedimento = '';
    public $OtrosImpuestosPagadosPedimento = '';
    public $FechaFolioFiscalVinculado = '';
    public $Mes = '';
    public $MontoTotalErogaciones = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
