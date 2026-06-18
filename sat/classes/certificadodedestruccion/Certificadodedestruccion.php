<?php

namespace SAT\Generated\certificadodedestruccion;

class Certificadodedestruccion extends \XMLS
{
    public $tagName = 'certificadodedestruccion';
    public $attributes = array (
  0 => 'Version',
  1 => 'Serie',
  2 => 'NumFolDesVeh',
);
    public $_sequence = array (
  0 => 'VehiculoDestruido',
  1 => 'InformacionAduanera',
);

    public $Version = '';
    public $Serie = '';
    public $NumFolDesVeh = '';
    public $VehiculoDestruido = '';
    public $InformacionAduanera = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
