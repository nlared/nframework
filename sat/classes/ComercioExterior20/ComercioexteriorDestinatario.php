<?php

namespace SAT\Generated\ComercioExterior20;

class ComercioexteriorDestinatario extends \XMLS
{
    public $tagName = 'Destinatario';
    public $attributes = array (
  0 => 'NumRegIdTrib',
  1 => 'Nombre',
);
    public $_sequence = array (
  0 => 'Domicilio',
);

    public $NumRegIdTrib = '';
    public $Nombre = '';
    public $Domicilio = [];

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
