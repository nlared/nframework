<?php

namespace SAT\Generated\cfdv1;

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
  6 => 'formaDePago',
  7 => 'noCertificado',
  8 => 'certificado',
);
    public $_sequence = array (
  0 => 'Emisor',
  1 => 'Receptor',
  2 => 'Conceptos',
  3 => 'Impuestos',
  4 => 'Addenda',
);

    public $version = '';
    public $serie = '';
    public $folio = '';
    public $fecha = '';
    public $sello = '';
    public $noAprobacion = '';
    public $formaDePago = '';
    public $noCertificado = '';
    public $certificado = '';
    public $Emisor = '';
    public $Receptor = '';
    public $Conceptos = '';
    public $Impuestos = '';
    public $Addenda = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
