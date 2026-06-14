<?php

namespace SAT\Generated\pfic;

class Pfintegrantecoordinado extends \XMLS
{
    public $tagName = 'PFintegranteCoordinado';
    public $attributes = array (
  0 => 'version',
  1 => 'ClaveVehicular',
  2 => 'Placa',
  3 => 'RFCPF',
);
    public $_sequence = array (
);

    public $version = '';
    public $ClaveVehicular = '';
    public $Placa = '';
    public $RFCPF = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
