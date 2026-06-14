<?php

namespace SAT\Generated\leyendasFisc;

class LeyendasfiscalesLeyenda extends \XMLS
{
    public $tagName = 'Leyenda';
    public $attributes = array (
  0 => 'disposicionFiscal',
  1 => 'norma',
  2 => 'textoLeyenda',
);
    public $_sequence = array (
);

    public $disposicionFiscal = '';
    public $norma = '';
    public $textoLeyenda = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
