<?
require 'common.php';
	$dialog=new AjaxDialog(['url'=>'/robots.txt']);
	$nframework->usecommon=true;
?>
<div class="container">
	<?=$dialog?>
</div>