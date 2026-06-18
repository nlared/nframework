<?php

namespace SAT\Generated\sectorfinanciero;

class Sectorfinanciero extends \XMLS
{
    public $tagName = 'SectorFinanciero';
    public $attributes = array (
  0 => 'Version',
  1 => 'IdFideicom',
  2 => 'NomFideicom',
  3 => 'DescripFideicom',
);
    public $_sequence = array (
);

    public $Version = '';
    public $IdFideicom = '';
    public $NomFideicom = '';
    public $DescripFideicom = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
