<?php
$developermode=true;
require '../common2.php';
	$dataset=new dataset([
    'collection'=>$m->{$config['sitedb']}->configs,
    '_id'=>'theme',
    'simpleid'=>true,
    'nameprefix'=>'data'
]);




$themes=new inputFiles([
	'dir'=> $_SERVER['DOCUMENT_ROOT'].'/nframework/themes/',
	'name'=>'productos',
	'upload'=>true,
	'delete'=>true,
	//'preview'=>true,
	'download'=>true, 
	'accept'=>'zip',
	'extension'=>__DIR__.DIRECTORY_SEPARATOR.'unzip.php',//https://www.w3schools.com/tags/att_input_accept.asp
	'onupload'=>'onupload',
	'ondelete'=>'ondelete',//*/
	'onlist'=>'onlist'
	//'countlimit'=>12,
	//'limit_time_end'=>$limit
]);
?>
<div class="container">
	<div class="bg-cyan fg-white p-3"><h4 class="m-0"><?= htmlspecialchars($nframework->language['themes'] ?? 'Temas') ?></h4></div>
	<div class="bg-white p-3 nf-scroll-x theme-files">
		<form method="post">
			<?=$themes?>
		</form>
	</div>
</div>
<style>
	/* Lista de temas: en pantallas angostas se desplaza en lugar de partir las cabeceras a media palabra. */
	.theme-files table { min-width: 420px; }
	.theme-files th { word-break: normal; }
</style>