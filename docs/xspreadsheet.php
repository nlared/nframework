<?php
require '../common2.php';
$excel=new xspreadsheet(['filename'=>__DIR__.'/test.xlsx']);
echo $excel;