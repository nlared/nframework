<?php

namespace SAT\Generated\ComercioExterior11;

class Comercioexterior extends \XMLS
{
    public $tagName = 'ComercioExterior';
    public $attributes = array (
  0 => 'Version',
  1 => 'MotivoTraslado',
  2 => 'TipoOperacion',
  3 => 'ClaveDePedimento',
  4 => 'CertificadoOrigen',
  5 => 'NumCertificadoOrigen',
  6 => 'NumeroExportadorConfiable',
  7 => 'Incoterm',
  8 => 'Subdivision',
  9 => 'Observaciones',
  10 => 'TipoCambioUSD',
  11 => 'TotalUSD',
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
    public $Propietario = [];
    public $Receptor = '';
    public $Destinatario = [];
    public $Mercancias = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
