<?php
$developermode=true;
require 'common.php';
?>
<h4>inputfiles</h4>
<?php

$limit=strtotime('+3 minutes');
$ahora=new DateTime();
$diferencia = $ahora->diff(new DateTime(date('Y-m-d\TH:i:s.u',$limit)));

$interval=explode(':',$diferencia->format('%d:%H:%i:%s'));
$contador='<div class="row"><div class="cell"><div class="countdown" data-role="countdown" data-days="'.$interval[0].'" data-hours="'.$interval[1].'" data-minutes="'.$interval[2].'" data-seconds="'.$interval[3].'" data-on-stop="salir();"></div></div></div>';
	 

$extension=__DIR__.'/upload_extension.php';
$directory=__DIR__.'/tmp/';

$_SESSION['images']['imagetest']=[
	'src'=>__DIR__.'/tmp/',
	'dst'=>__DIR__.'/tmp/w100/',
	'width'=>'200'
	];


$inputfile=new inputFile([
	'dir'=>$directory,
	'path'=>$directory.'img.png',
	'name'=>'producto',
	'upload'=>true,
	'delete'=>true,
	'preview'=>true,
	'download'=>true, 
	'accept'=>'image/*',
	'onDone'=><<<js
	if (data.files.length==1){
		$("#img-container").html('<img height="200" src="tmp/img.png?time='+Date.now()+'" alt="fff">');	
	}
js
,
//	'extension'=>$extension,//https://www.w3schools.com/tags/att_input_accept.asp
//	'onupload'=>'subir',
//	'ondelete'=>'borrar',
	'frontupload'=>'',
	'frontdelete'=>'',
	'limit_time_end'=>$limit
]);
$mid='testpdf';

$_SESSION['frompdf'][$mid]=[
	'filename'=>$directory.'/test.pdf',
	'directory'=>$directory.'/'
];

$pdffile=new inputFile([
	'dir'=>$directory,
	'path'=>$directory.'/test.pdf',
	'name'=>'pdf',
	'upload'=>true,
	'delete'=>true,
	'preview'=>true,
	'download'=>true, 
	'accept'=>'application/pdf',
	'onDone'=><<<js
	if (data.files.length==1){
		$.getJSON({
		url: "/images/frompdf/{$mid}/info.json",
		cache: false
		}).done(function(response) {
			var html='';
			for (i=1;i<=response.numberOfPages;i++){
				html+='<img height="200" src="/images/frompdf/{$mid}/200/282/'+i+'.png">';
				
			}
			console.log(response.numberOfPages);
			$("#pdf-container").html(html);	
		});
		
		
	}
js
,
//	'extension'=>$extension,//https://www.w3schools.com/tags/att_input_accept.asp
//	'onupload'=>'subir',
//	'ondelete'=>'borrar',
	'frontupload'=>'',
	'frontdelete'=>'',
	'limit_time_end'=>$limit
]);


$inputfiles=new inputFiles([
	'dir'=>$directory,
	'name'=>'productos',
	'upload'=>true,
	'delete'=>true,
	'preview'=>true,
	'download'=>true, 
	'accept'=>'image/*',
	'extension'=>$extension,//https://www.w3schools.com/tags/att_input_accept.asp
	'onupload'=>'subir',
	'ondelete'=>'borrar',
	'countlimit'=>12,
	'limit_time_end'=>$limit
]);
?>
<div class="container">
	<?=$contador.$inputfile?>
	<div id="img-container">
	
	</div>
	<?=$pdffile?>
	<div id="pdf-container">
	
	</div>
	<?=$inputfiles?>
</div>
<pre><code class="html">
<?=tocode(__file__) ?>
</code></pre>
<h4>upload_extension.php</h4>
<pre><code class="html">
<?=show_source($extension) ?>
</code></pre>