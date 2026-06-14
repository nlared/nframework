<?php

namespace SAT\Generated\retencionpagov2;

class Retenciones extends \XMLS
{
    public $tagName = 'Retenciones';
    public $attributes = array (
  0 => 'Version',
  1 => 'FolioInt',
  2 => 'Sello',
  3 => 'NoCertificado',
  4 => 'Certificado',
  5 => 'FechaExp',
  6 => 'LugarExpRetenc',
  7 => 'CveRetenc',
  8 => 'DescRetenc',
);
    public $_sequence = array (
  0 => 'CfdiRetenRelacionados',
  1 => 'Emisor',
  2 => 'Receptor',
  3 => 'Periodo',
  4 => 'Totales',
  5 => 'Complemento',
  6 => 'Addenda',
);

    public $Version = '';
    public $FolioInt = '';
    public $Sello = '';
    public $NoCertificado = '';
    public $Certificado = '';
    public $FechaExp = '';
    public $LugarExpRetenc = '';
    public $CveRetenc = '';
    public $DescRetenc = '';
    public $CfdiRetenRelacionados = '';
    public $Emisor = '';
    public $Receptor = '';
    public $Periodo = '';
    public $Totales = '';
    public $Complemento = '';
    public $Addenda = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
