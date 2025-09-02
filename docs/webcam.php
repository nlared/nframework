<?
require 'include.php';
$nframework->usecommon=true;
/*$signature =new Sigature_pad(['file'=>__DIR__.'test.png']);
echo $signature;*/

$webcam=new Webcam();
$webcam->path=__DIR__.'/test.jpg';
$webcam->exitpath='./';
echo $webcam;
?>