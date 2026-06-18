<?php

namespace SAT\Generated\CartaPorte;

class CartaporteFiguratransporteOperadoresOperador extends \XMLS
{
    public $tagName = 'Operador';
    public $attributes = array (
  0 => 'RFCOperador',
  1 => 'NumLicencia',
  2 => 'NombreOperador',
  3 => 'NumRegIdTribOperador',
  4 => 'ResidenciaFiscalOperador',
);
    public $_sequence = array (
  0 => 'Domicilio',
);

    public $RFCOperador = '';
    public $NumLicencia = '';
    public $NombreOperador = '';
    public $NumRegIdTribOperador = '';
    public $ResidenciaFiscalOperador = '';
    public $Domicilio = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
