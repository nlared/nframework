<?php

namespace SAT\Generated\TuristaPasajeroExtranjero;

class TuristapasajeroextranjeroDatostransito extends \XMLS
{
    public $tagName = 'datosTransito';
    public $attributes = array (
  0 => 'Via',
  1 => 'TipoId',
  2 => 'NumeroId',
  3 => 'Nacionalidad',
  4 => 'EmpresaTransporte',
  5 => 'IdTransporte',
);
    public $_sequence = array (
);

    public $Via = '';
    public $TipoId = '';
    public $NumeroId = '';
    public $Nacionalidad = '';
    public $EmpresaTransporte = '';
    public $IdTransporte = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
