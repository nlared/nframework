<?php

namespace SAT\Generated\cfdv33;

class Comprobante extends \XMLS
{
    public $tagName = 'Comprobante';
    public $attributes = array (
  0 => 'Version',
  1 => 'Serie',
  2 => 'Folio',
  3 => 'Fecha',
  4 => 'Sello',
  5 => 'FormaPago',
  6 => 'NoCertificado',
  7 => 'Certificado',
  8 => 'CondicionesDePago',
  9 => 'SubTotal',
  10 => 'Descuento',
  11 => 'Moneda',
  12 => 'TipoCambio',
  13 => 'Total',
  14 => 'TipoDeComprobante',
  15 => 'MetodoPago',
  16 => 'LugarExpedicion',
  17 => 'Confirmacion',
);
    public $_sequence = array (
  0 => 'CfdiRelacionados',
  1 => 'Emisor',
  2 => 'Receptor',
  3 => 'Conceptos',
  4 => 'Impuestos',
  5 => 'Complemento',
  6 => 'Addenda',
);

    public $Version = '';
    public $Serie = '';
    public $Folio = '';
    public $Fecha = '';
    public $Sello = '';
    public $FormaPago = '';
    public $NoCertificado = '';
    public $Certificado = '';
    public $CondicionesDePago = '';
    public $SubTotal = '';
    public $Descuento = '';
    public $Moneda = '';
    public $TipoCambio = '';
    public $Total = '';
    public $TipoDeComprobante = '';
    public $MetodoPago = '';
    public $LugarExpedicion = '';
    public $Confirmacion = '';
    public $CfdiRelacionados = '';
    public $Emisor = '';
    public $Receptor = '';
    public $Conceptos = '';
    public $Impuestos = '';
    public $Complemento = [];
    public $Addenda = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
