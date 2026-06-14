<?php

namespace SAT\Generated\CartaPorte;

class CartaporteFiguratransporteArrendatario extends \XMLS
{
    public $tagName = 'Arrendatario';
    public $attributes = array (
  0 => 'RFCArrendatario',
  1 => 'NombreArrendatario',
  2 => 'NumRegIdTribArrendatario',
  3 => 'ResidenciaFiscalArrendatario',
);
    public $_sequence = array (
  0 => 'Domicilio',
);

    public $RFCArrendatario = '';
    public $NombreArrendatario = '';
    public $NumRegIdTribArrendatario = '';
    public $ResidenciaFiscalArrendatario = '';
    public $Domicilio = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
