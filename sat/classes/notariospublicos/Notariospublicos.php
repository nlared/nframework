<?php

namespace SAT\Generated\notariospublicos;

class Notariospublicos extends \XMLS
{
    public $tagName = 'NotariosPublicos';
    public $attributes = array (
  0 => 'Version',
);
    public $_sequence = array (
  0 => 'DescInmuebles',
  1 => 'DatosOperacion',
  2 => 'DatosNotario',
  3 => 'DatosEnajenante',
  4 => 'DatosAdquiriente',
);

    public $Version = '';
    public $DescInmuebles = '';
    public $DatosOperacion = '';
    public $DatosNotario = '';
    public $DatosEnajenante = '';
    public $DatosAdquiriente = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
