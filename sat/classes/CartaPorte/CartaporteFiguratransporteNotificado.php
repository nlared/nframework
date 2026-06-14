<?php

namespace SAT\Generated\CartaPorte;

class CartaporteFiguratransporteNotificado extends \XMLS
{
    public $tagName = 'Notificado';
    public $attributes = array (
  0 => 'RFCNotificado',
  1 => 'NombreNotificado',
  2 => 'NumRegIdTribNotificado',
  3 => 'ResidenciaFiscalNotificado',
);
    public $_sequence = array (
  0 => 'Domicilio',
);

    public $RFCNotificado = '';
    public $NombreNotificado = '';
    public $NumRegIdTribNotificado = '';
    public $ResidenciaFiscalNotificado = '';
    public $Domicilio = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
