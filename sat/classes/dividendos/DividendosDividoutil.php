<?php

namespace SAT\Generated\dividendos;

class DividendosDividoutil extends \XMLS
{
    public $tagName = 'DividOUtil';
    public $attributes = array (
  0 => 'CveTipDivOUtil',
  1 => 'MontISRAcredRetMexico',
  2 => 'MontISRAcredRetExtranjero',
  3 => 'MontRetExtDivExt',
  4 => 'TipoSocDistrDiv',
  5 => 'MontISRAcredNal',
  6 => 'MontDivAcumNal',
  7 => 'MontDivAcumExt',
);
    public $_sequence = array (
);

    public $CveTipDivOUtil = '';
    public $MontISRAcredRetMexico = '';
    public $MontISRAcredRetExtranjero = '';
    public $MontRetExtDivExt = '';
    public $TipoSocDistrDiv = '';
    public $MontISRAcredNal = '';
    public $MontDivAcumNal = '';
    public $MontDivAcumExt = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
