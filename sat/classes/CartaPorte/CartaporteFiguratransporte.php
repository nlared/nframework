<?php

namespace SAT\Generated\CartaPorte;

class CartaporteFiguratransporte extends \XMLS
{
    public $tagName = 'FiguraTransporte';
    public $attributes = array (
  0 => 'CveTransporte',
);
    public $_sequence = array (
  0 => 'Operadores',
  1 => 'Propietario',
  2 => 'Arrendatario',
  3 => 'Notificado',
);

    public $CveTransporte = '';
    public $Operadores = [];
    public $Propietario = [];
    public $Arrendatario = [];
    public $Notificado = [];

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
