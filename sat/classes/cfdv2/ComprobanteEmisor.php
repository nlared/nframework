<?php

namespace SAT\Generated\cfdv2;

class ComprobanteEmisor extends \XMLS
{
    public $tagName = 'Emisor';
    public $attributes = array (
  0 => 'rfc',
  1 => 'nombre',
);
    public $_sequence = array (
  0 => 'DomicilioFiscal',
  1 => 'ExpedidoEn',
);

    public $rfc = '';
    public $nombre = '';
    public $DomicilioFiscal = '';
    public $ExpedidoEn = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
