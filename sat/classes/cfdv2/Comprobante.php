<?php

namespace SAT\Generated\cfdv2;

class Comprobante extends \XMLS
{
    public $tagName = 'Comprobante';
    public $attributes = array (
  0 => 'version',
  1 => 'serie',
  2 => 'folio',
  3 => 'fecha',
  4 => 'sello',
  5 => 'noAprobacion',
  6 => 'anoAprobacion',
  7 => 'formaDePago',
  8 => 'noCertificado',
  9 => 'certificado',
  10 => 'condicionesDePago',
  11 => 'subTotal',
  12 => 'descuento',
  13 => 'motivoDescuento',
  14 => 'total',
  15 => 'metodoDePago',
  16 => 'tipoDeComprobante',
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
    public $noAprobacion = '';
    public $anoAprobacion = '';
    public $formaDePago = '';
    public $noCertificado = '';
    public $certificado = '';
    public $condicionesDePago = '';
    public $subTotal = '';
    public $descuento = '';
    public $motivoDescuento = '';
    public $total = '';
    public $metodoDePago = '';
    public $tipoDeComprobante = '';
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
