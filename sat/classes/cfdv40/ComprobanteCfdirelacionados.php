<?php

namespace SAT\Generated\cfdv40;

class ComprobanteCfdirelacionados extends \XMLS
{
    public $tagName = 'CfdiRelacionados';
    public $attributes = array (
  0 => 'TipoRelacion',
);
    public $_sequence = array (
  0 => 'CfdiRelacionado',
);

    public $TipoRelacion = '';
    public $CfdiRelacionado = [];

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
