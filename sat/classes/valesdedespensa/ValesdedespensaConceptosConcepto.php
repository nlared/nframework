<?php

namespace SAT\Generated\valesdedespensa;

class ValesdedespensaConceptosConcepto extends \XMLS
{
    public $tagName = 'Concepto';
    public $attributes = array (
  0 => 'identificador',
  1 => 'fecha',
  2 => 'rfc',
  3 => 'curp',
  4 => 'nombre',
  5 => 'numSeguridadSocial',
  6 => 'importe',
);
    public $_sequence = array (
);

    public $identificador = '';
    public $fecha = '';
    public $rfc = '';
    public $curp = '';
    public $nombre = '';
    public $numSeguridadSocial = '';
    public $importe = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
