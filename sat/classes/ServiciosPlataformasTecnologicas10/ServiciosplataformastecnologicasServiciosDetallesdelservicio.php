<?php

namespace SAT\Generated\ServiciosPlataformasTecnologicas10;

class ServiciosplataformastecnologicasServiciosDetallesdelservicio extends \XMLS
{
    public $tagName = 'DetallesDelServicio';
    public $attributes = array (
  0 => 'FormaPagoServ',
  1 => 'TipoDeServ',
  2 => 'SubTipServ',
  3 => 'RFCTerceroAutorizado',
  4 => 'FechaServ',
  5 => 'PrecioServSinIVA',
);
    public $_sequence = array (
  0 => 'ImpuestosTrasladadosdelServicio',
  1 => 'ContribucionGubernamental',
  2 => 'ComisionDelServicio',
);

    public $FormaPagoServ = '';
    public $TipoDeServ = '';
    public $SubTipServ = '';
    public $RFCTerceroAutorizado = '';
    public $FechaServ = '';
    public $PrecioServSinIVA = '';
    public $ImpuestosTrasladadosdelServicio = '';
    public $ContribucionGubernamental = '';
    public $ComisionDelServicio = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
