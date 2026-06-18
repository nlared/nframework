<?php

namespace SAT\Generated\retencionpagov2;

class RetencionesTotalesImpretenidos extends \XMLS
{
    public $tagName = 'ImpRetenidos';
    public $attributes = array (
  0 => 'BaseRet',
  1 => 'ImpuestoRet',
  2 => 'MontoRet',
  3 => 'TipoPagoRet',
);
    public $_sequence = array (
);

    public $BaseRet = '';
    public $ImpuestoRet = '';
    public $MontoRet = '';
    public $TipoPagoRet = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
