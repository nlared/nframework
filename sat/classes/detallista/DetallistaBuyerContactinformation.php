<?php

namespace SAT\Generated\detallista;

class DetallistaBuyerContactinformation extends \XMLS
{
    public $tagName = 'contactInformation';
    public $attributes = array (
);
    public $_sequence = array (
  0 => 'personOrDepartmentName',
);

    public $personOrDepartmentName = '';

    public function __construct(array $ops = [])
    {
        parent::__construct($ops);
    }
}
