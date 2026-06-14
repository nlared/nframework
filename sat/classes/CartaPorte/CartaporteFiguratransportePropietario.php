<?php

namespace SAT\Generated\CartaPorte;

class CartaporteFiguratransportePropietario extends \XMLS
{
    public $tagName = 'Propietario';
    public $attributes = array (
  0 => 'RFCPropietario',
  1 => 'NombrePropietario',
  2 => 'NumRegIdTribPropietario',
  3 => 'ResidenciaFiscalPropietario',
);
    public $_sequence = array (
  0 => 'Domicilio',
);

    public $RFCPropietario = '';
    public $NombrePropietario = '';
    public $NumRegIdTribPropietario = '';
    public $ResidenciaFiscalPropietario = '';
    public $Domicilio = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
