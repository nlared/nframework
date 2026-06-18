<?php

namespace SAT\Generated\CartaPorte31;

class CartaporteMercanciasAutotransporteSeguros extends \XMLS
{
    public $tagName = 'Seguros';
    public $attributes = array (
  0 => 'AseguraRespCivil',
  1 => 'PolizaRespCivil',
  2 => 'AseguraMedAmbiente',
  3 => 'PolizaMedAmbiente',
  4 => 'AseguraCarga',
  5 => 'PolizaCarga',
  6 => 'PrimaSeguro',
);
    public $_sequence = array (
);

    public $AseguraRespCivil = '';
    public $PolizaRespCivil = '';
    public $AseguraMedAmbiente = '';
    public $PolizaMedAmbiente = '';
    public $AseguraCarga = '';
    public $PolizaCarga = '';
    public $PrimaSeguro = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
