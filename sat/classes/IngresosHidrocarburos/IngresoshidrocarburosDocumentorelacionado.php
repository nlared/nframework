<?php

namespace SAT\Generated\IngresosHidrocarburos;

class IngresoshidrocarburosDocumentorelacionado extends \XMLS
{
    public $tagName = 'DocumentoRelacionado';
    public $attributes = array (
  0 => 'FolioFiscalVinculado',
  1 => 'FechaFolioFiscalVinculado',
  2 => 'Mes',
);
    public $_sequence = array (
);

    public $FolioFiscalVinculado = '';
    public $FechaFolioFiscalVinculado = '';
    public $Mes = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
