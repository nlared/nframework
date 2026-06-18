<?php

namespace SAT\Generated\ComercioExterior10;

class ComercioexteriorMercanciasMercanciaDescripcionesespecificas extends \XMLS
{
    public $tagName = 'DescripcionesEspecificas';
    public $attributes = array (
  0 => 'Marca',
  1 => 'Modelo',
  2 => 'SubModelo',
  3 => 'NumeroSerie',
);
    public $_sequence = array (
);

    public $Marca = '';
    public $Modelo = '';
    public $SubModelo = '';
    public $NumeroSerie = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
