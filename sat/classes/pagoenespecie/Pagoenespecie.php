<?php

namespace SAT\Generated\pagoenespecie;

class Pagoenespecie extends \XMLS
{
    public $tagName = 'PagoEnEspecie';
    public $attributes = array (
  0 => 'Version',
  1 => 'CvePIC',
  2 => 'FolioSolDon',
  3 => 'PzaArtNombre',
  4 => 'PzaArtTecn',
  5 => 'PzaArtAProd',
  6 => 'PzaArtDim',
);
    public $_sequence = array (
);

    public $Version = '';
    public $CvePIC = '';
    public $FolioSolDon = '';
    public $PzaArtNombre = '';
    public $PzaArtTecn = '';
    public $PzaArtAProd = '';
    public $PzaArtDim = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
