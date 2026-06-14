<?php

namespace SAT\Generated\ServiciosPlataformasTecnologicas10;

class Serviciosplataformastecnologicas extends \XMLS
{
    public $tagName = 'ServiciosPlataformasTecnologicas';
    public $attributes = array (
  0 => 'Version',
  1 => 'Periodicidad',
  2 => 'NumServ',
  3 => 'MonTotServSIVA',
  4 => 'TotalIVATrasladado',
  5 => 'TotalIVARetenido',
  6 => 'TotalISRRetenido',
  7 => 'DifIVAEntregadoPrestServ',
  8 => 'MonTotalporUsoPlataforma',
  9 => 'MonTotalContribucionGubernamental',
);
    public $_sequence = array (
  0 => 'Servicios',
);

    public $Version = '';
    public $Periodicidad = '';
    public $NumServ = '';
    public $MonTotServSIVA = '';
    public $TotalIVATrasladado = '';
    public $TotalIVARetenido = '';
    public $TotalISRRetenido = '';
    public $DifIVAEntregadoPrestServ = '';
    public $MonTotalporUsoPlataforma = '';
    public $MonTotalContribucionGubernamental = '';
    public $Servicios = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
