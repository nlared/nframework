<?php

namespace SAT\Generated\implocal;

class Impuestoslocales extends \XMLS
{
    public $tagName = 'ImpuestosLocales';
    public $attributes = array (
  0 => 'version',
  1 => 'TotaldeRetenciones',
  2 => 'TotaldeTraslados',
);
    public $_sequence = array (
  0 => 'RetencionesLocales',
  1 => 'TrasladosLocales',
);

    public $version = '';
    public $TotaldeRetenciones = '';
    public $TotaldeTraslados = '';
    public $RetencionesLocales = '';
    public $TrasladosLocales = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
