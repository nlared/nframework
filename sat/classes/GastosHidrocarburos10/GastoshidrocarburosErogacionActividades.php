<?php

namespace SAT\Generated\GastosHidrocarburos10;

class GastoshidrocarburosErogacionActividades extends \XMLS
{
    public $tagName = 'Actividades';
    public $attributes = array (
  0 => 'ActividadRelacionada',
);
    public $_sequence = array (
  0 => 'SubActividades',
);

    public $ActividadRelacionada = '';
    public $SubActividades = [];

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
