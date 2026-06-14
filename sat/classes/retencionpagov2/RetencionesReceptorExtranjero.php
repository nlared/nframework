<?php

namespace SAT\Generated\retencionpagov2;

class RetencionesReceptorExtranjero extends \XMLS
{
    public $tagName = 'Extranjero';
    public $attributes = array (
  0 => 'NumRegIdTribR',
  1 => 'NomDenRazSocR',
);
    public $_sequence = array (
);

    public $NumRegIdTribR = '';
    public $NomDenRazSocR = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
