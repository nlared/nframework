<?php

namespace SAT\Generated\ine11;

class Ine extends \XMLS
{
    public $tagName = 'INE';
    public $attributes = array (
  0 => 'Version',
  1 => 'TipoProceso',
  2 => 'TipoComite',
  3 => 'IdContabilidad',
);
    public $_sequence = array (
  0 => 'Entidad',
);

    public $Version = '';
    public $TipoProceso = '';
    public $TipoComite = '';
    public $IdContabilidad = '';
    public $Entidad = [];

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
