<?php

namespace SAT\Generated\ComercioExterior10;

class ComercioexteriorDestinatario extends \XMLS
{
    public $tagName = 'Destinatario';
    public $attributes = array (
  0 => 'NumRegIdTrib',
  1 => 'Rfc',
  2 => 'Curp',
  3 => 'Nombre',
);
    public $_sequence = array (
  0 => 'Domicilio',
);

    public $NumRegIdTrib = '';
    public $Rfc = '';
    public $Curp = '';
    public $Nombre = '';
    public $Domicilio = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
