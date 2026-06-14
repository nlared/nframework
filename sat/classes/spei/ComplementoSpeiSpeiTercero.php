<?php

namespace SAT\Generated\spei;

class ComplementoSpeiSpeiTercero extends \XMLS
{
    public $tagName = 'SPEI_Tercero';
    public $attributes = array (
  0 => 'FechaOperacion',
  1 => 'Hora',
  2 => 'ClaveSPEI',
  3 => 'sello',
  4 => 'numeroCertificado',
  5 => 'cadenaCDA',
);
    public $_sequence = array (
);

    public $FechaOperacion = '';
    public $Hora = '';
    public $ClaveSPEI = '';
    public $sello = '';
    public $numeroCertificado = '';
    public $cadenaCDA = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
