<?php

namespace SAT\Generated\Pagos20;

class PagosPagoImpuestosp extends \XMLS
{
    public $tagName = 'ImpuestosP';
    public $attributes = array (
);
    public $_sequence = array (
  0 => 'RetencionesP',
  1 => 'TrasladosP',
);

    public $RetencionesP = '';
    public $TrasladosP = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
