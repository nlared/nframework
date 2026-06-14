<?php

namespace SAT\Generated\CartaPorte31;

class CartaporteMercanciasAutotransporte extends \XMLS
{
    public $tagName = 'Autotransporte';
    public $attributes = array (
  0 => 'PermSCT',
  1 => 'NumPermisoSCT',
);
    public $_sequence = array (
  0 => 'IdentificacionVehicular',
  1 => 'Seguros',
  2 => 'Remolques',
);

    public $PermSCT = '';
    public $NumPermisoSCT = '';
    public $IdentificacionVehicular = '';
    public $Seguros = '';
    public $Remolques = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
