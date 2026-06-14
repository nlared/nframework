<?php

namespace SAT\Generated\nomina12;

class NominaPercepcionesPercepcionHorasextra extends \XMLS
{
    public $tagName = 'HorasExtra';
    public $attributes = array (
  0 => 'Dias',
  1 => 'TipoHoras',
  2 => 'HorasExtra',
  3 => 'ImportePagado',
);
    public $_sequence = array (
);

    public $Dias = '';
    public $TipoHoras = '';
    public $HorasExtra = '';
    public $ImportePagado = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
