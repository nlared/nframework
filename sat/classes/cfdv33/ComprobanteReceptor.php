<?php

namespace SAT\Generated\cfdv33;

class ComprobanteReceptor extends \XMLS
{
    public $tagName = 'Receptor';
    public $attributes = array (
  0 => 'Rfc',
  1 => 'Nombre',
  2 => 'ResidenciaFiscal',
  3 => 'NumRegIdTrib',
  4 => 'UsoCFDI',
);
    public $_sequence = array (
);

    public $Rfc = '';
    public $Nombre = '';
    public $ResidenciaFiscal = '';
    public $NumRegIdTrib = '';
    public $UsoCFDI = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
