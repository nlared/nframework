<?php

namespace SAT\Generated\retencionpagov2;

class RetencionesEmisor extends \XMLS
{
    public $tagName = 'Emisor';
    public $attributes = array (
  0 => 'RfcE',
  1 => 'NomDenRazSocE',
  2 => 'RegimenFiscalE',
);
    public $_sequence = array (
);

    public $RfcE = '';
    public $NomDenRazSocE = '';
    public $RegimenFiscalE = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
