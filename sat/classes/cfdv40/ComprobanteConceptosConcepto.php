<?php

namespace SAT\Generated\cfdv40;

class ComprobanteConceptosConcepto extends \XMLS
{
    public $tagName = 'Concepto';
    public $attributes = array (
  0 => 'ClaveProdServ',
  1 => 'NoIdentificacion',
  2 => 'Cantidad',
  3 => 'ClaveUnidad',
  4 => 'Unidad',
  5 => 'Descripcion',
  6 => 'ValorUnitario',
  7 => 'Importe',
  8 => 'Descuento',
  9 => 'ObjetoImp',
);
    public $_sequence = array (
  0 => 'Impuestos',
  1 => 'ACuentaTerceros',
  2 => 'InformacionAduanera',
  3 => 'CuentaPredial',
  4 => 'ComplementoConcepto',
  5 => 'Parte',
);

    public $ClaveProdServ = '';
    public $NoIdentificacion = '';
    public $Cantidad = '';
    public $ClaveUnidad = '';
    public $Unidad = '';
    public $Descripcion = '';
    public $ValorUnitario = '';
    public $Importe = '';
    public $Descuento = '';
    public $ObjetoImp = '';
    public $Impuestos = '';
    public $ACuentaTerceros = '';
    public $InformacionAduanera = [];
    public $CuentaPredial = [];
    public $ComplementoConcepto = '';
    public $Parte = [];

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
