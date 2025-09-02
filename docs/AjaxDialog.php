<?
require '../common2.php';
	$dialog=new AjaxDialog(['url'=>'/robots.txt']);
	$nframework->usecommon=true;
?>
<div class="container">
	<?=$dialog?>
</div>