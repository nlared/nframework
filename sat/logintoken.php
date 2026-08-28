<?
require 'include.php';
$_SESSION['logintoken']=uniqid(true);
//$_SESSION['logintoken']='||4.0|2022-03-02T13:50:36|03|00001000000511303847|18600.00|MXN|21576.00|I|01|PUE|25000|FORJ7904092D1|JUAN ENRIQUE FLORES RODRIGUEZ|626|RME181019QK0|3RA DE MEXICO|25160|601|G03|81112103|24|LH|LH|Sistema digital para la verificación de un código de barras de tipo PDF417. Proyecto entregado.|775.000000|18600.000000|02|18600.000000|002|Tasa|0.160000|2976.000000|18600|002|Tasa|0.160000|2976.00|2976.00||';
//header('Content-Type: application/json; charset=utf-8');
$result=['token'=>$_SESSION['logintoken']];