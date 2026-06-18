<?php

namespace SAT\Generated\cfdv40;

class ComprobanteEmisor extends \XMLS
{
    public $tagName = 'Emisor';
    public $attributes = array (
  0 => 'Rfc',
  1 => 'Nombre',
  2 => 'RegimenFiscal',
  3 => 'FacAtrAdquirente',
);
    public $_sequence = array (
);

    public $Rfc = '';
    public $Nombre = '';
    public $RegimenFiscal = '';
    public $FacAtrAdquirente = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
