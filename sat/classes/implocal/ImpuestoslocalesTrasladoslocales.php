<?php

namespace SAT\Generated\implocal;

class ImpuestoslocalesTrasladoslocales extends \XMLS
{
    public $tagName = 'TrasladosLocales';
    public $attributes = array (
  0 => 'ImpLocTrasladado',
  1 => 'TasadeTraslado',
  2 => 'Importe',
);
    public $_sequence = array (
);

    public $ImpLocTrasladado = '';
    public $TasadeTraslado = '';
    public $Importe = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
