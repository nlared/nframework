<?php
require 'include.php';
//Default Configuration
$CONFIG = '{"lang":"'.$nframework->langshort.'","error_reporting":true,"show_hidden":false,"hide_Cols":false,"theme":"light"}';
$developermode=true;
$nfshutdowndisable=true;
$noobfuscate=true;
$nfjavaobfuscatedisable=true;
$nframework->isAjax=true;

define('FM_EMBED', true);
define('FM_SELF_URL', $_SERVER['PHP_SELF']);

if(!$user->in('admins')){
	die('sin permiso');
}
unset($nframework);
require 'filemanager.php';