<?php

namespace SAT\Generated\cfdv40;

class ComprobanteConceptosConceptoParte extends \XMLS
{
    public $tagName = 'Parte';
    public $attributes = array (
  0 => 'ClaveProdServ',
  1 => 'NoIdentificacion',
  2 => 'Cantidad',
  3 => 'Unidad',
  4 => 'Descripcion',
  5 => 'ValorUnitario',
  6 => 'Importe',
);
    public $_sequence = array (
  0 => 'InformacionAduanera',
);

    public $ClaveProdServ = '';
    public $NoIdentificacion = '';
    public $Cantidad = '';
    public $Unidad = '';
    public $Descripcion = '';
    public $ValorUnitario = '';
    public $Importe = '';
    public $InformacionAduanera = [];

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
