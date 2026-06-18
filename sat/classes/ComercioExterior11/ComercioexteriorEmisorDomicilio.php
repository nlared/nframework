<?php

namespace SAT\Generated\ComercioExterior11;

class ComercioexteriorEmisorDomicilio extends \XMLS
{
    public $tagName = 'Domicilio';
    public $attributes = array (
  0 => 'Calle',
  1 => 'NumeroExterior',
  2 => 'NumeroInterior',
  3 => 'Colonia',
  4 => 'Localidad',
  5 => 'Referencia',
  6 => 'Municipio',
  7 => 'Estado',
  8 => 'Pais',
  9 => 'CodigoPostal',
);
    public $_sequence = array (
);

    public $Calle = '';
    public $NumeroExterior = '';
    public $NumeroInterior = '';
    public $Colonia = '';
    public $Localidad = '';
    public $Referencia = '';
    public $Municipio = '';
    public $Estado = '';
    public $Pais = '';
    public $CodigoPostal = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
