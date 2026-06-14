<?php

namespace SAT\Generated\retencionpagov2;

class RetencionesReceptorNacional extends \XMLS
{
    public $tagName = 'Nacional';
    public $attributes = array (
  0 => 'RfcR',
  1 => 'NomDenRazSocR',
  2 => 'CurpR',
  3 => 'DomicilioFiscalR',
);
    public $_sequence = array (
);

    public $RfcR = '';
    public $NomDenRazSocR = '';
    public $CurpR = '';
    public $DomicilioFiscalR = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
