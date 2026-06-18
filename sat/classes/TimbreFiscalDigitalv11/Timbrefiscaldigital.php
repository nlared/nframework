<?php

namespace SAT\Generated\TimbreFiscalDigitalv11;

class Timbrefiscaldigital extends \XMLS
{
    public $tagName = 'TimbreFiscalDigital';
    public $attributes = array (
  0 => 'Version',
  1 => 'UUID',
  2 => 'FechaTimbrado',
  3 => 'RfcProvCertif',
  4 => 'Leyenda',
  5 => 'SelloCFD',
  6 => 'NoCertificadoSAT',
  7 => 'SelloSAT',
);
    public $_sequence = array (
);

    public $Version = '';
    public $UUID = '';
    public $FechaTimbrado = '';
    public $RfcProvCertif = '';
    public $Leyenda = '';
    public $SelloCFD = '';
    public $NoCertificadoSAT = '';
    public $SelloSAT = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
