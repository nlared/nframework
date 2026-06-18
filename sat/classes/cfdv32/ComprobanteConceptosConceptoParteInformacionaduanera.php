<?php

namespace SAT\Generated\cfdv32;

class ComprobanteConceptosConceptoParteInformacionaduanera extends \XMLS
{
    public $tagName = 'InformacionAduanera';
    public $attributes = array (
  0 => 'numero',
  1 => 'fecha',
  2 => 'aduana',
);
    public $_sequence = array (
);

    public $numero = '';
    public $fecha = '';
    public $aduana = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
