<?php

namespace SAT\Generated\retencionpagov1;

class RetencionesTotales extends \XMLS
{
    public $tagName = 'Totales';
    public $attributes = array (
  0 => 'montoTotOperacion',
  1 => 'montoTotGrav',
  2 => 'montoTotExent',
  3 => 'montoTotRet',
);
    public $_sequence = array (
  0 => 'ImpRetenidos',
);

    public $montoTotOperacion = '';
    public $montoTotGrav = '';
    public $montoTotExent = '';
    public $montoTotRet = '';
    public $ImpRetenidos = [];

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
