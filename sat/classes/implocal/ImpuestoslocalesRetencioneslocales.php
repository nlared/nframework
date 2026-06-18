<?php

namespace SAT\Generated\implocal;

class ImpuestoslocalesRetencioneslocales extends \XMLS
{
    public $tagName = 'RetencionesLocales';
    public $attributes = array (
  0 => 'ImpLocRetenido',
  1 => 'TasadeRetencion',
  2 => 'Importe',
);
    public $_sequence = array (
);

    public $ImpLocRetenido = '';
    public $TasadeRetencion = '';
    public $Importe = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
