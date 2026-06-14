<?php

namespace SAT\Generated\CartaPorte;

class CartaporteMercanciasAutotransportefederal extends \XMLS
{
    public $tagName = 'AutotransporteFederal';
    public $attributes = array (
  0 => 'PermSCT',
  1 => 'NumPermisoSCT',
  2 => 'NombreAseg',
  3 => 'NumPolizaSeguro',
);
    public $_sequence = array (
  0 => 'IdentificacionVehicular',
  1 => 'Remolques',
);

    public $PermSCT = '';
    public $NumPermisoSCT = '';
    public $NombreAseg = '';
    public $NumPolizaSeguro = '';
    public $IdentificacionVehicular = '';
    public $Remolques = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
