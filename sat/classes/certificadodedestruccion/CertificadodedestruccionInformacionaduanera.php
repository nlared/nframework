<?php

namespace SAT\Generated\certificadodedestruccion;

class CertificadodedestruccionInformacionaduanera extends \XMLS
{
    public $tagName = 'InformacionAduanera';
    public $attributes = array (
  0 => 'NumPedImp',
  1 => 'Fecha',
  2 => 'Aduana',
);
    public $_sequence = array (
);

    public $NumPedImp = '';
    public $Fecha = '';
    public $Aduana = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
