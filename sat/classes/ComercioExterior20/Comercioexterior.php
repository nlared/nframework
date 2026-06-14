<?php

namespace SAT\Generated\ComercioExterior20;

class Comercioexterior extends \XMLS
{
    public $tagName = 'ComercioExterior';
    public $attributes = array (
  0 => 'Version',
  1 => 'MotivoTraslado',
  2 => 'ClaveDePedimento',
  3 => 'CertificadoOrigen',
  4 => 'NumCertificadoOrigen',
  5 => 'NumeroExportadorConfiable',
  6 => 'Incoterm',
  7 => 'Observaciones',
  8 => 'TipoCambioUSD',
  9 => 'TotalUSD',
);
    public $_sequence = array (
  0 => 'Emisor',
  1 => 'Propietario',
  2 => 'Receptor',
  3 => 'Destinatario',
  4 => 'Mercancias',
);

    public $Version = '';
    public $MotivoTraslado = '';
    public $ClaveDePedimento = '';
    public $CertificadoOrigen = '';
    public $NumCertificadoOrigen = '';
    public $NumeroExportadorConfiable = '';
    public $Incoterm = '';
    public $Observaciones = '';
    public $TipoCambioUSD = '';
    public $TotalUSD = '';
    public $Emisor = '';
    public $Propietario = [];
    public $Receptor = '';
    public $Destinatario = [];
    public $Mercancias = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
