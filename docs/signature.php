<?
require 'include.php';
$nframework->usecommon=true;
$webcam =new Webcam();
$webcam->exitpath='./webcamshow.php';
$webcam->path=__DIR__.'/test.png';
echo $webcam;
?>