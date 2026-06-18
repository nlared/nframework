<?php

namespace SAT\Generated\TimbreFiscalDigital;

class Timbrefiscaldigital extends \XMLS
{
    public $tagName = 'TimbreFiscalDigital';
    public $attributes = array (
  0 => 'version',
  1 => 'UUID',
  2 => 'FechaTimbrado',
  3 => 'selloCFD',
  4 => 'noCertificadoSAT',
  5 => 'selloSAT',
);
    public $_sequence = array (
);

    public $version = '';
    public $UUID = '';
    public $FechaTimbrado = '';
    public $selloCFD = '';
    public $noCertificadoSAT = '';
    public $selloSAT = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
