<?php

namespace SAT\Generated\cfdv2;

class ComprobanteReceptor extends \XMLS
{
    public $tagName = 'Receptor';
    public $attributes = array (
  0 => 'rfc',
  1 => 'nombre',
);
    public $_sequence = array (
  0 => 'Domicilio',
);

    public $rfc = '';
    public $nombre = '';
    public $Domicilio = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
