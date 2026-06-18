<?php

namespace SAT\Generated\retencionpagov1;

class Retenciones extends \XMLS
{
    public $tagName = 'Retenciones';
    public $attributes = array (
  0 => 'Version',
  1 => 'FolioInt',
  2 => 'Sello',
  3 => 'NumCert',
  4 => 'Cert',
  5 => 'FechaExp',
  6 => 'CveRetenc',
  7 => 'DescRetenc',
);
    public $_sequence = array (
  0 => 'Emisor',
  1 => 'Receptor',
  2 => 'Periodo',
  3 => 'Totales',
  4 => 'Complemento',
  5 => 'Addenda',
);

    public $Version = '';
    public $FolioInt = '';
    public $Sello = '';
    public $NumCert = '';
    public $Cert = '';
    public $FechaExp = '';
    public $CveRetenc = '';
    public $DescRetenc = '';
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
