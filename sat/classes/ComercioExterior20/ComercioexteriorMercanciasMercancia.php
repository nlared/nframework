<?php

namespace SAT\Generated\ComercioExterior20;

class ComercioexteriorMercanciasMercancia extends \XMLS
{
    public $tagName = 'Mercancia';
    public $attributes = array (
  0 => 'NoIdentificacion',
  1 => 'FraccionArancelaria',
  2 => 'CantidadAduana',
  3 => 'UnidadAduana',
  4 => 'ValorUnitarioAduana',
  5 => 'ValorDolares',
);
    public $_sequence = array (
  0 => 'DescripcionesEspecificas',
);

    public $NoIdentificacion = '';
    public $FraccionArancelaria = '';
    public $CantidadAduana = '';
    public $UnidadAduana = '';
    public $ValorUnitarioAduana = '';
    public $ValorDolares = '';
    public $DescripcionesEspecificas = [];

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
