<?php

namespace SAT\Generated\Pagos20;

class PagosPagoDoctorelacionadoImpuestosdrRetencionesdrRetenciondr extends \XMLS
{
    public $tagName = 'RetencionDR';
    public $attributes = array (
  0 => 'BaseDR',
  1 => 'ImpuestoDR',
  2 => 'TipoFactorDR',
  3 => 'TasaOCuotaDR',
  4 => 'ImporteDR',
);
    public $_sequence = array (
);

    public $BaseDR = '';
    public $ImpuestoDR = '';
    public $TipoFactorDR = '';
    public $TasaOCuotaDR = '';
    public $ImporteDR = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
