<?php

namespace SAT\Generated\notariospublicos;

class NotariospublicosDatosenajenanteDatosenajenantescopscDatosenajenantecopsc extends \XMLS
{
    public $tagName = 'DatosEnajenanteCopSC';
    public $attributes = array (
  0 => 'Nombre',
  1 => 'ApellidoPaterno',
  2 => 'ApellidoMaterno',
  3 => 'RFC',
  4 => 'CURP',
  5 => 'Porcentaje',
);
    public $_sequence = array (
);

    public $Nombre = '';
    public $ApellidoPaterno = '';
    public $ApellidoMaterno = '';
    public $RFC = '';
    public $CURP = '';
    public $Porcentaje = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
