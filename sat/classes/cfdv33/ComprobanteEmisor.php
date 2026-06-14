<?php

namespace SAT\Generated\cfdv33;

class ComprobanteEmisor extends \XMLS
{
    public $tagName = 'Emisor';
    public $attributes = array (
  0 => 'Rfc',
  1 => 'Nombre',
  2 => 'RegimenFiscal',
);
    public $_sequence = array (
);

    public $Rfc = '';
    public $Nombre = '';
    public $RegimenFiscal = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
