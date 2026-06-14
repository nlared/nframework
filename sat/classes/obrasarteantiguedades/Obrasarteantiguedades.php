<?php

namespace SAT\Generated\obrasarteantiguedades;

class Obrasarteantiguedades extends \XMLS
{
    public $tagName = 'obrasarteantiguedades';
    public $attributes = array (
  0 => 'Version',
  1 => 'TipoBien',
  2 => 'OtrosTipoBien',
  3 => 'TituloAdquirido',
  4 => 'OtrosTituloAdquirido',
  5 => 'Subtotal',
  6 => 'IVA',
  7 => 'FechaAdquisicion',
  8 => 'Caracter__sticasDeObraoPieza',
);
    public $_sequence = array (
);

    public $Version = '';
    public $TipoBien = '';
    public $OtrosTipoBien = '';
    public $TituloAdquirido = '';
    public $OtrosTituloAdquirido = '';
    public $Subtotal = '';
    public $IVA = '';
    public $FechaAdquisicion = '';
    public $Caracter__sticasDeObraoPieza = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
