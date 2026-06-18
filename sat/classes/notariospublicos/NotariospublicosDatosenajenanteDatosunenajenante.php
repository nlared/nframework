<?php

namespace SAT\Generated\notariospublicos;

class NotariospublicosDatosenajenanteDatosunenajenante extends \XMLS
{
    public $tagName = 'DatosUnEnajenante';
    public $attributes = array (
  0 => 'Nombre',
  1 => 'ApellidoPaterno',
  2 => 'ApellidoMaterno',
  3 => 'RFC',
  4 => 'CURP',
);
    public $_sequence = array (
);

    public $Nombre = '';
    public $ApellidoPaterno = '';
    public $ApellidoMaterno = '';
    public $RFC = '';
    public $CURP = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
