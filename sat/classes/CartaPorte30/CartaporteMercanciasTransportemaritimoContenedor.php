<?php

namespace SAT\Generated\CartaPorte30;

class CartaporteMercanciasTransportemaritimoContenedor extends \XMLS
{
    public $tagName = 'Contenedor';
    public $attributes = array (
  0 => 'TipoContenedor',
  1 => 'MatriculaContenedor',
  2 => 'NumPrecinto',
  3 => 'IdCCPRelacionado',
  4 => 'PlacaVMCCP',
  5 => 'FechaCertificacionCCP',
);
    public $_sequence = array (
);

    public $TipoContenedor = '';
    public $MatriculaContenedor = '';
    public $NumPrecinto = '';
    public $IdCCPRelacionado = '';
    public $PlacaVMCCP = '';
    public $FechaCertificacionCCP = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
