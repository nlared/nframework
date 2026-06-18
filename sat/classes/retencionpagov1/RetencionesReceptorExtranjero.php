<?php

namespace SAT\Generated\retencionpagov1;

class RetencionesReceptorExtranjero extends \XMLS
{
    public $tagName = 'Extranjero';
    public $attributes = array (
  0 => 'NumRegIdTrib',
  1 => 'NomDenRazSocR',
);
    public $_sequence = array (
);

    public $NumRegIdTrib = '';
    public $NomDenRazSocR = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
