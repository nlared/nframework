<?php

namespace SAT\Generated\cfdv40;

class ComprobanteConceptosConceptoAcuentaterceros extends \XMLS
{
    public $tagName = 'ACuentaTerceros';
    public $attributes = array (
  0 => 'RfcACuentaTerceros',
  1 => 'NombreACuentaTerceros',
  2 => 'RegimenFiscalACuentaTerceros',
  3 => 'DomicilioFiscalACuentaTerceros',
);
    public $_sequence = array (
);

    public $RfcACuentaTerceros = '';
    public $NombreACuentaTerceros = '';
    public $RegimenFiscalACuentaTerceros = '';
    public $DomicilioFiscalACuentaTerceros = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
