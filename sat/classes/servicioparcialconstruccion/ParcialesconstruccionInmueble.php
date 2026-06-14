<?php

namespace SAT\Generated\servicioparcialconstruccion;

class ParcialesconstruccionInmueble extends \XMLS
{
    public $tagName = 'Inmueble';
    public $attributes = array (
  0 => 'Calle',
  1 => 'NoExterior',
  2 => 'NoInterior',
  3 => 'Colonia',
  4 => 'Localidad',
  5 => 'Referencia',
  6 => 'Municipio',
  7 => 'Estado',
  8 => 'CodigoPostal',
);
    public $_sequence = array (
);

    public $Calle = '';
    public $NoExterior = '';
    public $NoInterior = '';
    public $Colonia = '';
    public $Localidad = '';
    public $Referencia = '';
    public $Municipio = '';
    public $Estado = '';
    public $CodigoPostal = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
