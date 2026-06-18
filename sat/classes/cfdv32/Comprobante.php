<?php

namespace SAT\Generated\cfdv32;

class Comprobante extends \XMLS
{
    public $tagName = 'Comprobante';
    public $attributes = array (
  0 => 'version',
  1 => 'serie',
  2 => 'folio',
  3 => 'fecha',
  4 => 'sello',
  5 => 'formaDePago',
  6 => 'noCertificado',
  7 => 'certificado',
  8 => 'condicionesDePago',
  9 => 'subTotal',
  10 => 'descuento',
  11 => 'motivoDescuento',
  12 => 'TipoCambio',
  13 => 'Moneda',
  14 => 'total',
  15 => 'tipoDeComprobante',
  16 => 'metodoDePago',
  17 => 'LugarExpedicion',
  18 => 'NumCtaPago',
  19 => 'FolioFiscalOrig',
  20 => 'SerieFolioFiscalOrig',
  21 => 'FechaFolioFiscalOrig',
  22 => 'MontoFolioFiscalOrig',
);
    public $_sequence = array (
  0 => 'Emisor',
  1 => 'Receptor',
  2 => 'Conceptos',
  3 => 'Impuestos',
  4 => 'Complemento',
  5 => 'Addenda',
);

    public $version = '';
    public $serie = '';
    public $folio = '';
    public $fecha = '';
    public $sello = '';
    public $formaDePago = '';
    public $noCertificado = '';
    public $certificado = '';
    public $condicionesDePago = '';
    public $subTotal = '';
    public $descuento = '';
    public $motivoDescuento = '';
    public $TipoCambio = '';
    public $Moneda = '';
    public $total = '';
    public $tipoDeComprobante = '';
    public $metodoDePago = '';
    public $LugarExpedicion = '';
    public $NumCtaPago = '';
    public $FolioFiscalOrig = '';
    public $SerieFolioFiscalOrig = '';
    public $FechaFolioFiscalOrig = '';
    public $MontoFolioFiscalOrig = '';
    public $Emisor = '';
    public $Receptor = '';
    public $Conceptos = '';
    public $Impuestos = '';
    public $Complemento = '';
    public $Addenda = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
