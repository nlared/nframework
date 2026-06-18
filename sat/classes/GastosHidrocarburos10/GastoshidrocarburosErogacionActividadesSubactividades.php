<?php

namespace SAT\Generated\GastosHidrocarburos10;

class GastoshidrocarburosErogacionActividadesSubactividades extends \XMLS
{
    public $tagName = 'SubActividades';
    public $attributes = array (
  0 => 'SubActividadRelacionada',
);
    public $_sequence = array (
  0 => 'Tareas',
);

    public $SubActividadRelacionada = '';
    public $Tareas = [];

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
