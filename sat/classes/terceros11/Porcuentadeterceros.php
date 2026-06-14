<?php

namespace SAT\Generated\terceros11;

class Porcuentadeterceros extends \XMLS
{
    public $tagName = 'PorCuentadeTerceros';
    public $attributes = array (
  0 => 'version',
  1 => 'rfc',
  2 => 'nombre',
);
    public $_sequence = array (
  0 => 'InformacionFiscalTercero',
  1 => 'Impuestos',
);

    public $version = '';
    public $rfc = '';
    public $nombre = '';
    public $InformacionFiscalTercero = '';
    public $Impuestos = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
