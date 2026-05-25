<?php
require 'common.php';
$excel = new xspreadsheet(['filename' => __DIR__ . '/test.xlsx']);
echo $excel;
