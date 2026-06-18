<?php

namespace SAT\Generated\cfdv2;

class ComprobanteReceptorDomicilio extends \XMLS
{
    public $tagName = 'Domicilio';
    public $attributes = array (
  0 => 'calle',
  1 => 'noExterior',
  2 => 'noInterior',
  3 => 'colonia',
  4 => 'localidad',
  5 => 'referencia',
  6 => 'municipio',
  7 => 'estado',
  8 => 'pais',
  9 => 'codigoPostal',
);
    public $_sequence = array (
);

    public $calle = '';
    public $noExterior = '';
    public $noInterior = '';
    public $colonia = '';
    public $localidad = '';
    public $referencia = '';
    public $municipio = '';
    public $estado = '';
    public $pais = '';
    public $codigoPostal = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
