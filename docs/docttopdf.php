<?
require 'common.php';
$wordtemplate = new \PhpOffice\PhpWord\TemplateProcessor('template.docx');
$wordtemplate->setValue('name', 'John Doe');
$wordtemplate->setValue('datetime',  date('Y-m-d H:i:s'));
$nframework->wordTemplateOutPdf($wordtemplate, 'output.pdf');
