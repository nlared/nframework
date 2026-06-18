<?php

namespace SAT\Generated\pagosaextranjeros;

class PagosaextranjerosNobeneficiario extends \XMLS
{
    public $tagName = 'NoBeneficiario';
    public $attributes = array (
  0 => 'PaisDeResidParaEfecFisc',
  1 => 'ConceptoPago',
  2 => 'DescripcionConcepto',
);
    public $_sequence = array (
);

    public $PaisDeResidParaEfecFisc = '';
    public $ConceptoPago = '';
    public $DescripcionConcepto = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
