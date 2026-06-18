<?php

namespace SAT\Generated\ComercioExterior10;

class Comercioexterior extends \XMLS
{
    public $tagName = 'ComercioExterior';
    public $attributes = array (
  0 => 'Version',
  1 => 'TipoOperacion',
  2 => 'ClaveDePedimento',
  3 => 'CertificadoOrigen',
  4 => 'NumCertificadoOrigen',
  5 => 'NumeroExportadorConfiable',
  6 => 'Incoterm',
  7 => 'Subdivision',
  8 => 'Observaciones',
  9 => 'TipoCambioUSD',
  10 => 'TotalUSD',
);
    public $_sequence = array (
  0 => 'Emisor',
  1 => 'Receptor',
  2 => 'Destinatario',
  3 => 'Mercancias',
);

    public $Version = '';
    public $TipoOperacion = '';
    public $ClaveDePedimento = '';
    public $CertificadoOrigen = '';
    public $NumCertificadoOrigen = '';
    public $NumeroExportadorConfiable = '';
    public $Incoterm = '';
    public $Subdivision = '';
    public $Observaciones = '';
    public $TipoCambioUSD = '';
    public $TotalUSD = '';
    public $Emisor = '';
    public $Receptor = '';
    public $Destinatario = '';
    public $Mercancias = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
