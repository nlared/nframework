<?php

namespace SAT\Generated\notariospublicos;

class NotariospublicosDescinmueblesDescinmueble extends \XMLS
{
    public $tagName = 'DescInmueble';
    public $attributes = array (
  0 => 'TipoInmueble',
  1 => 'Calle',
  2 => 'NoExterior',
  3 => 'NoInterior',
  4 => 'Colonia',
  5 => 'Localidad',
  6 => 'Referencia',
  7 => 'Municipio',
  8 => 'Estado',
  9 => 'Pais',
  10 => 'CodigoPostal',
);
    public $_sequence = array (
);

    public $TipoInmueble = '';
    public $Calle = '';
    public $NoExterior = '';
    public $NoInterior = '';
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
