<?php

namespace SAT\Generated\operacionesconderivados;

class Operacionesconderivados extends \XMLS
{
    public $tagName = 'Operacionesconderivados';
    public $attributes = array (
  0 => 'Version',
  1 => 'MontGanAcum',
  2 => 'MontPerdDed',
);
    public $_sequence = array (
);

    public $Version = '';
    public $MontGanAcum = '';
    public $MontPerdDed = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
