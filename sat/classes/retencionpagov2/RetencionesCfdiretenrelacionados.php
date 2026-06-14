<?php

namespace SAT\Generated\retencionpagov2;

class RetencionesCfdiretenrelacionados extends \XMLS
{
    public $tagName = 'CfdiRetenRelacionados';
    public $attributes = array (
  0 => 'TipoRelacion',
  1 => 'UUID',
);
    public $_sequence = array (
);

    public $TipoRelacion = '';
    public $UUID = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
