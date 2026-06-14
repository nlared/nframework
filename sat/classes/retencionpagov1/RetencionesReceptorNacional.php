<?php

namespace SAT\Generated\retencionpagov1;

class RetencionesReceptorNacional extends \XMLS
{
    public $tagName = 'Nacional';
    public $attributes = array (
  0 => 'RFCRecep',
  1 => 'NomDenRazSocR',
  2 => 'CURPR',
);
    public $_sequence = array (
);

    public $RFCRecep = '';
    public $NomDenRazSocR = '';
    public $CURPR = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
