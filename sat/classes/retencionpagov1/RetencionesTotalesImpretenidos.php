<?php

namespace SAT\Generated\retencionpagov1;

class RetencionesTotalesImpretenidos extends \XMLS
{
    public $tagName = 'ImpRetenidos';
    public $attributes = array (
  0 => 'BaseRet',
  1 => 'Impuesto',
  2 => 'montoRet',
  3 => 'TipoPagoRet',
);
    public $_sequence = array (
);

    public $BaseRet = '';
    public $Impuesto = '';
    public $montoRet = '';
    public $TipoPagoRet = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
