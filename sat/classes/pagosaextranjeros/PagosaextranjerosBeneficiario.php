<?php

namespace SAT\Generated\pagosaextranjeros;

class PagosaextranjerosBeneficiario extends \XMLS
{
    public $tagName = 'Beneficiario';
    public $attributes = array (
  0 => 'RFC',
  1 => 'CURP',
  2 => 'NomDenRazSocB',
  3 => 'ConceptoPago',
  4 => 'DescripcionConcepto',
);
    public $_sequence = array (
);

    public $RFC = '';
    public $CURP = '';
    public $NomDenRazSocB = '';
    public $ConceptoPago = '';
    public $DescripcionConcepto = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
