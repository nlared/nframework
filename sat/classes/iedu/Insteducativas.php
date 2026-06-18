<?php

namespace SAT\Generated\iedu;

class Insteducativas extends \XMLS
{
    public $tagName = 'instEducativas';
    public $attributes = array (
  0 => 'version',
  1 => 'nombreAlumno',
  2 => 'CURP',
  3 => 'nivelEducativo',
  4 => 'autRVOE',
  5 => 'rfcPago',
);
    public $_sequence = array (
);

    public $version = '';
    public $nombreAlumno = '';
    public $CURP = '';
    public $nivelEducativo = '';
    public $autRVOE = '';
    public $rfcPago = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
