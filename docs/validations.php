<?
require 'include.php';

$nframework->usecommon=true;


$dataset=new dataset(
    [
    'collection'=>$m->nframework->exampledata,
    '_id'=>$_GET['_id'],
    'simpleid'=>false,
    'nameprefix'=>'data']
);


$directory=__DIR__.'/documentos/'.$user->_id;
if(!file_exists($directory)){
	mkdir($directory,0777);
}

$mid='credencial';
$_SESSION['frompdf'][$mid]=[
	'filename'=>$directory.'/credencial.pdf',
	'directory'=>$directory.'/'
];
$credencial=new inputFile([
	'dir'=>$directory,
	'path'=>$directory.'/credencial.pdf',
	'name'=>'credencial',
	'upload'=>true,
	'delete'=>true,
	'preview'=>true,
	'download'=>true, 
	'accept'=>'*.pdf',
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



$expediente=new inputText(['dataset'=>& $dataset,'caption'=>'1.- Escribe tu número de expediente','field'=>'expediente','pattern'=>'^[0-9]{6}$','required'=>true]);
$nocedula=new inputText(['dataset'=>& $dataset,'field'=>'nocedula','required'=>true]);




if ($_POST['op']=='Guardar') {
    
    try {
        $dataset->save();
    } catch (Exception $e) {
    	$result['error']=$e->getMessage();
    }
}else{

?>
<div class="container p-5">
	<div class=" p-3"><h4>ACTUALIZACIÓN DE DATOS DE EMPLEADOS PARA AUDITORIAS</h4></div>
	<div class="bg-white p-3">
	<?=secureform()?>ite p-3">
	<?=secureform()?>
		<div class="grid">
			<div class="row">
				<div class="cell">1.- Escribe tu número de expediente<?=$expediente?></div>
			</div>
			<div class="row">
				<div class="cell">2.- Si cuentas con Cédula Profesional, escribe su número<?=$nocedula?></div>
			</div>
			
			<div class="row">
				<div class="cell">3.- Por favor suba en archivo tipo PDF su credencial del INE actualizada por dos lados en una sola hoja)
					<?=$credencial?>
					Límite de número de archivos: 1 <br>
					Límite de tamaño del archivo único: 10MB<br>
					Tipos de archivo permitidos: PDF
				</div>
			</div>
		
			<div class="row">
				<div class="cell-md-2 offset-md-8"><a href="datatableajax.php" class="button primary btn btn-primary w-100"><span class="mif-exit"></span>&nbsp;Cerrar</a></div>
				<div class="cell-md-2"><button class="button success btn btn-success secureop  w-100" value="save"><span class="mif-floppy-disk"></span>&nbsp;Guardar</button></div>
			</div>
		</div>
	</form>
	</div>
</div>
<?}?>