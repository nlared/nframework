<?php

namespace SAT\Generated\retencionpagov1;

class RetencionesEmisor extends \XMLS
{
    public $tagName = 'Emisor';
    public $attributes = array (
  0 => 'RFCEmisor',
  1 => 'NomDenRazSocE',
  2 => 'CURPE',
);
    public $_sequence = array (
);

    public $RFCEmisor = '';
    public $NomDenRazSocE = '';
    public $CURPE = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
