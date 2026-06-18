<?php

namespace SAT\Generated\notariospublicos;

class NotariospublicosDatosoperacion extends \XMLS
{
    public $tagName = 'DatosOperacion';
    public $attributes = array (
  0 => 'NumInstrumentoNotarial',
  1 => 'FechaInstNotarial',
  2 => 'MontoOperacion',
  3 => 'Subtotal',
  4 => 'IVA',
);
    public $_sequence = array (
);

    public $NumInstrumentoNotarial = '';
    public $FechaInstNotarial = '';
    public $MontoOperacion = '';
    public $Subtotal = '';
    public $IVA = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
