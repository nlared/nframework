<?php

namespace SAT\Generated\ServiciosPlataformasTecnologicas10;

class ServiciosplataformastecnologicasServiciosDetallesdelservicioContribuciongubernamental extends \XMLS
{
    public $tagName = 'ContribucionGubernamental';
    public $attributes = array (
  0 => 'ImpContrib',
  1 => 'EntidadDondePagaLaContribucion',
);
    public $_sequence = array (
);

    public $ImpContrib = '';
    public $EntidadDondePagaLaContribucion = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
