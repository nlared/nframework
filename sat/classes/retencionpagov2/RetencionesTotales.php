<?php

namespace SAT\Generated\retencionpagov2;

class RetencionesTotales extends \XMLS
{
    public $tagName = 'Totales';
    public $attributes = array (
  0 => 'MontoTotOperacion',
  1 => 'MontoTotGrav',
  2 => 'MontoTotExent',
  3 => 'MontoTotRet',
  4 => 'UtilidadBimestral',
  5 => 'ISRCorrespondiente',
);
    public $_sequence = array (
  0 => 'ImpRetenidos',
);

    public $MontoTotOperacion = '';
    public $MontoTotGrav = '';
    public $MontoTotExent = '';
    public $MontoTotRet = '';
    public $UtilidadBimestral = '';
    public $ISRCorrespondiente = '';
    public $ImpRetenidos = [];

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
