<?php

namespace SAT\Generated\ServiciosPlataformasTecnologicas10;

class ServiciosplataformastecnologicasServiciosDetallesdelservicioComisiondelservicio extends \XMLS
{
    public $tagName = 'ComisionDelServicio';
    public $attributes = array (
  0 => 'Base',
  1 => 'Porcentaje',
  2 => 'Importe',
);
    public $_sequence = array (
);

    public $Base = '';
    public $Porcentaje = '';
    public $Importe = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
