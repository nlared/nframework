<?php

namespace SAT\Generated\ServiciosPlataformasTecnologicas10;

class ServiciosplataformastecnologicasServiciosDetallesdelservicioImpuestostrasladadosdelservicio extends \XMLS
{
    public $tagName = 'ImpuestosTrasladadosdelServicio';
    public $attributes = array (
  0 => 'Base',
  1 => 'Impuesto',
  2 => 'TipoFactor',
  3 => 'TasaCuota',
  4 => 'Importe',
);
    public $_sequence = array (
);

    public $Base = '';
    public $Impuesto = '';
    public $TipoFactor = '';
    public $TasaCuota = '';
    public $Importe = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
