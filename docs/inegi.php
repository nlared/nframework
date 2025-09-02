<?php
if (empty($_GET['_id'])) {
    $newid=new MongoDB\BSON\ObjectID();
    header('Location: ?_id='.$newid);
    exit();
}
require 'include.php';
$dataset=new dataset(
    [
    'collection'=>$m->{$config['sitedb']}->inegi,
    '_id'=>$_GET['_id'],
    'simpleid'=>false,
    'nameprefix'=>'data']
);
$developermode=true;
$mapa=new mapmarker(['dataset'=>&$dataset,'field'=>'mapa','caption'=>'Mapa:','required'=>true,'onchange'=>'buscard();']);

$estado=new inputtext(['dataset'=>&$dataset,'field'=>'estado','caption'=>'Estado:','data-autocomplete'=>'uno,dos','autocomplete'=>'none','required'=>true]);
$municipio=new inputtext(['dataset'=>&$dataset,'field'=>'municipio','caption'=>'Municipio:','data-autocomplete'=>'uno,dos','autocomplete'=>'none','required'=>true]);
$localidad=new inputtext(['dataset'=>&$dataset,'field'=>'localidad','caption'=>'Localidad:','data-autocomplete'=>'uno,dos','autocomplete'=>'none','required'=>true]);
$asentamiento=new inputtext(['dataset'=>&$dataset,'field'=>'asentamiento','caption'=>'Asentamiento:','data-autocomplete'=>'uno,dos','autocomplete'=>'none','required'=>true]);
$vialidad=new inputtext(['dataset'=>&$dataset,'field'=>'vialidad','caption'=>'Vialidad:','data-autocomplete'=>'uno,dos','autocomplete'=>'none','required'=>true]);

$noext=new inputtext(['dataset'=>&$dataset,'field'=>'noext','caption'=>'No. ext:','required'=>true]);
$noint=new inputtext(['dataset'=>&$dataset,'field'=>'noint','caption'=>'No. int:',]);


if ($nframework->isAjax()) {
	if ($_POST['op']=='save') {
        $session = $m->startSession();
        $session->startTransaction();
        try {
            $result=[
                'error'=>$dataset->save(),
           
            ];
            $session->commitTransaction();
        } catch (Exception $e) {
            $session->abortTransaction();
            $result=[
            	'error'=>$e->getMessage()
        	];
        }
    }
} else {
	$nframework->usecommon=true;
	$javas->addjs('llamarApiDenueBus();', 'ready'); 
?>
<div class="container p-5">
	<div class="bg-cyan fg-white p-3"><h4>Dirección INEGI</h4></div>
	<div class="bg-white p-3">
	<?=secureform()?>
		<div class="grid">
			
			<div class="row">
				<div class="cell-md-6"><?=$estado?></div>
				<div class="cell-md-6"><?=$municipio?></div>
			</div>
			<div class="row">
				<div class="cell-md-6"><?=$localidad?></div>
				<div class="cell-md-6"><?=$asentamiento?></div>
			</div>
			
			<div class="row">
				<div class="cell-md-6"><?=$vialidad?></div>
				<div class="cell-md-3"><?=$noext?></div>
				<div class="cell-md-3"><?=$noint?></div>
			</div>
			
			<div class="row">
				<div class="cell"><?=$mapa?></div>
				<div class="cell" id="datainegi"></div>
			</div>
			<div class="row">
				<div class="cell-md-2 offset-md-8"><a href="./" class="button primary w-100"><span class="mif-exit"></span>&nbsp;Cerrar</a></div>
				<div class="cell-md-2"><button class="button secureop success w-100" value="save"><span class="mif-floppy-disk"></span>&nbsp;Guardar</button></div>
			</div>
		</div>
		
	</form>
	</div>
</div>
<script>

var estadosn=[];
var estadosc=[];
var estadoi;
var municipiosn=[];
var municipiosc=[];
var municipioi;
var localidadesn=[];
var localidadesc=[];
var localidadi;
var asentamientosn=[];
var asentamientosc=[];
var asentamientoi;



function llamarApiDenueBus(){
	/*var festados=function (data) {return '<option value="' + data.cve_agee + '">' + data.nom_agee + '</option>' };
	var fmunicipios=function (data) {return '<option value="' + data.cve_agem + '">' + data.nom_agem + '</option>' };
	var flocalidades=function (data) {return '<option value="' + data.cve_loc + '">' + data.nom_loc + '</option>' };
	var fasentamientos=function (data) {return '<option value="' + data.cve_asen + '">' + data.nom_asen + '</option>' };
	loadselect("#CVE_ENT","https://gaia.inegi.org.mx/wscatgeo/mgee/",festados);
	loadselect("#CVE_MUN","https://gaia.inegi.org.mx/wscatgeo/mgem/05",fmunicipios);
	loadselect("#CVE_LOC","https://gaia.inegi.org.mx/wscatgeo/localidades/05030",flocalidades);
	loadselect("#CVE_ASE","https://gaia.inegi.org.mx/wscatgeo/asentamientos/05/030",fasentamientos);*/
	
	$.ajax({
        type: 'GET',
        url: 'https://gaia.inegi.org.mx/wscatgeo/mgee/',
        cache: false,
        async: false,
        dataType: "json",
        success: function (data) {
        	var input= $('#data_estado').data('input');
        	estadosn=[];
        	estadosc=[];
            for (var i = 0; i < data.datos.length; i++) {
            	estadosn.push(data.datos[i].nom_agee);
            	estadosc.push(data.datos[i].cve_agee);
            }
            input.autocomplete=estadosn;
			val=$('#data_estado').val();
            console.log(val);
            load_mun(val,false);
            
        }, complete: function (xhr, status) {
            $('#spinner').hide();
        }
    });
}


function load_mun(val,clear){
	estadoi=$.inArray(val,estadosn);
 	if (estadoi!=-1){
 		//console.log(val,index,estadosc[index]);
 		$.ajax({
	        type: 'GET',
	        url: 'https://gaia.inegi.org.mx/wscatgeo/mgem/'+estadosc[estadoi],
	        cache: false,
	        async: false,
	        dataType: "json",
	        success: function (data) {
	        	var input= $('#data_municipio').data('input');
	        	municipiosn=[];
	        	municipiosc=[];
	            for (var i = 0; i < data.datos.length; i++) {
	            	municipiosn.push(data.datos[i].nom_agem);
	            	municipiosc.push(data.datos[i].cve_agem);
	            }
	            input.autocomplete=municipiosn;
	            if(clear){
	            	$('#data_municipio').val('');
	            }else{
	            	val=$('#data_municipio').val();
	            	load_loc(val,false);
	            }
	        }, complete: function (xhr, status) {
	            $('#spinner').hide();
	        }
	    });
 	}
}


 
$('#data_estado').change(function(){
 	var val=$(this).val();
 	load_mun(val,true);
 });
 
 function load_loc(val,clear){
 	municipioi=$.inArray(val,municipiosn);
 	if (municipioi!=-1){
 		//console.log(val,index,estadosc[index]);
 		$.ajax({
	        type: 'GET',
	        url: 'https://gaia.inegi.org.mx/wscatgeo/localidades/'+estadosc[estadoi]+municipiosc[municipioi],
	        cache: false,
	        async: false,
	        dataType: "json",
	        success: function (data) {
	        	var input= $('#data_localidad').data('input');
	        	localidadesn=[];
	        	localidadesc=[];
	            for (var i = 0; i < data.datos.length; i++) {
	            	localidadesn.push(data.datos[i].nom_loc);
	            	localidadesc.push(data.datos[i].cve_loc);
	            }
	            input.autocomplete=localidadesn;
	            if(clear){
	            	$('#data_localidad').val('');
	            }else{
	            	val=$('#data_localidad').val();
	            	load_asent(val,false);
	            }
	        }, complete: function (xhr, status) {
	            $('#spinner').hide();
	        }
	    });
 	}
 }
 
  
 $('#data_municipio').change(function(){
 	var val=$(this).val();
 	load_loc(val,true);
 });
 
 
 function load_asent(val,clear){
 	localidadi=$.inArray(val,localidadesn);
 	if (localidadi!=-1){
 		//console.log(val,index,estadosc[index]);
 		$.ajax({
	        type: 'GET',
	        url: 'https://gaia.inegi.org.mx/wscatgeo/asentamientos/'+estadosc[estadoi]+municipiosc[municipioi]+localidadesc[localidadi],
	        cache: false,
	        async: false,
	        dataType: "json",
	        success: function (data) {
	        	var input= $('#data_asentamiento').data('input');
	        	asentamientosn=[];
	        	asentamientosc=[];
	            for (var i = 0; i < data.datos.length; i++) {
	            	//data.datos[i].tipo_asen+' '
	            	asentamientosn.push(data.datos[i].nom_asen);
	            	asentamientosc.push(data.datos[i].cve_asen);
	            }
	            input.autocomplete=asentamientosn;
	            if(clear){
	            	$('#data_asentamiento').val('');
	            }
	        }, complete: function (xhr, status) {
	            $('#spinner').hide();
	        }
	    });
	    $.ajax({
	        type: 'GET',
	        url: 'https://gaia.inegi.org.mx/wscatgeo/vialidades/'+estadosc[estadoi]+municipiosc[municipioi]+localidadesc[localidadi],
	        cache: false,
	        async: false,
	        dataType: "json",
	        success: function (data) {
	        	var input= $('#data_vialidad').data('input');
	        	viasn=[];
	        	viasc=[];
	            for (var i = 0; i < data.datos.length; i++) {
	            	if(data.datos[i].nom_via!==''){
		            	if($.inArray(data.datos[i].nom_via,viasn)==-1){
		            		viasn.push(data.datos[i].nom_via);
		            		viasc.push(data.datos[i].cve_via);
		            	}
	            	}
	            }
	            console.log(viasn);
	            input.autocomplete=viasn;
	            if(clear){
	            	$('#data_vialidad').val('');
	            }
	        }, complete: function (xhr, status) {
	            $('#spinner').hide();
	        }
	    });
	    
 	}
 }
 
 
 $('#data_localidad').change(function(){
 	var val=$(this).val();
 	load_asent(val,true);
 });
 
 /*
 $('#data_asentamiento').change(function(){
 	var val=$(this).val();
 	asentamientoi=$.inArray(val,asentamientosn);
 	if (asentamientoi!=-1){
 		//console.log(val,index,estadosc[index]);
 		$.ajax({
	        type: 'GET',
	        url: 'https://gaia.inegi.org.mx/wscatgeo/asentamientos/'+estadosc[estadoi]+municipiosc[municipioi]+localidadesc[localidadi],
	        cache: false,
	        async: false,
	        dataType: "json",
	        success: function (data) {
	        	var input= $('#data_vialidad').data('input');
	        	vialidadsn=[];
	        	vialidadsc=[];
	            for (var i = 0; i < data.datos.length; i++) {
	            	vialidadsn.push(data.datos[i].nom_asen);
	            	vialidadsc.push(data.datos[i].cve_asen);
	            }
	            input.autocomplete=vialidadsn;
	            
	        }, complete: function (xhr, status) {
	            $('#spinner').hide();
	        }
	    });
 	}
 });*/


function buscard(){
	var lat=$("#data_mapa_lat").val();
	var lng=$("#data_mapa_lng").val();
	$.ajax({
        type: 'GET',
        url: 'https://www.inegi.org.mx/app/api/denue/v1/consulta/Buscar/todos/'+lat+','+lng+'/30/d21aa3b0-cfac-41ea-b858-5b25870bfc99',
        cache: false,
        async: false,
        dataType: "json",
        success: function (data) {
        	$("#datainegi").text(JSON.stringify(data));
            
        }, complete: function (xhr, status) {
            $('#spinner').hide();
        }
    });
 }
 
 function loadselect(id,url,func){
 		$.ajax({
        type: 'GET',
        url: url,
        cache: false,
        async: false,
        dataType: "json",
        success: function (data) {
        	var sel=Metro.getPlugin(id,'select');
        	var datas='<option value="">Seleccione..</option>';
            for (var i = 0; i < data.datos.length; i++) {
            	datas+=func(data.datos[i]);
            }
            sel.data(datas);
        }, error: function (objeto, tipo, causa) {
            if (objeto.status == "404" || objeto.status == "200") {
                var func = function () {
                    window.location.href = "inicioweb.jsp";
                };
                $.avisoMsg("La sesión ha caducado", func);
            } else {
                alert(tipo + "  " + causa + "\nStatusfw:" + objeto.status);
            }
        }, complete: function (xhr, status) {
            $('#spinner').hide();
        }
    });
 }

</script>



<?}?>