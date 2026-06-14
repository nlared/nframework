<?php

namespace SAT\Generated\cfdv40;

class ComprobanteReceptor extends \XMLS
{
    public $tagName = 'Receptor';
    public $attributes = array (
  0 => 'Rfc',
  1 => 'Nombre',
  2 => 'DomicilioFiscalReceptor',
  3 => 'ResidenciaFiscal',
  4 => 'NumRegIdTrib',
  5 => 'RegimenFiscalReceptor',
  6 => 'UsoCFDI',
);
    public $_sequence = array (
);

    public $Rfc = '';
    public $Nombre = '';
    public $DomicilioFiscalReceptor = '';
    public $ResidenciaFiscal = '';
    public $NumRegIdTrib = '';
    public $RegimenFiscalReceptor = '';
    public $UsoCFDI = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
