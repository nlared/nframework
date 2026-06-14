<?php

namespace SAT\Generated\TuristaPasajeroExtranjero;

class Turistapasajeroextranjero extends \XMLS
{
    public $tagName = 'TuristaPasajeroExtranjero';
    public $attributes = array (
  0 => 'version',
  1 => 'fechadeTransito',
  2 => 'tipoTransito',
);
    public $_sequence = array (
  0 => 'datosTransito',
);

    public $version = '';
    public $fechadeTransito = '';
    public $tipoTransito = '';
    public $datosTransito = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
