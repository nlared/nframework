<?php
if (empty($_GET['_id'])) {
    $newid=new MongoDB\BSON\ObjectID();
    header('Location: ?_id='.$newid);
    exit();
}
require '../common2.php';



$dataset=new dataset(
    [
    'collection'=>$m->{$config['sitedb']}->users,
    '_id'=>$_GET['_id'],
    'simpleid'=>false,
    'nameprefix'=>'data']
);

$password=new inputText(['id'=>'password','name'=>"password",'caption'=>$nframework->language['password'].':','required'=>true]);
$username=new inputText(['dataset'=>&$dataset,'field'=>'username','caption'=>$nframework->language['username'].':','required'=>true]);

if(file_exists(include $_SERVER['DOCUMENT_ROOT'].'/admins/users/user.php')){
	include $_SERVER['DOCUMENT_ROOT'].'/admins/users/user.php';
}




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
    if ($_POST['op']=='password') {
    	$dataset->password=hash('sha512', $_POST['password']);
        $result=[
            'error'=>'',
            'js'=>'alert("Contraseña cambiada");'
        ];
    }
} else {
	$nframework->usecommon=true;
	
	$datatable=new Table();
	$datatable->Ajax([
	    'id'=>'sessions',
	    'db'=>$config['sitedb'],
	    'collection'=>'sessions',
	    'header'=>'<th>Last accesed</th><th>id</th>',
	    'pipeline'=>[[
	    	'$match'=>[
	    		'_id'=>['$in'=>(array)$user->sessions]
	    		]
	    	],
	    	[
	    	'$addFields'=>[
	    		'last_accessed'=>['$toString'=>'$last_accessed']
	    		]	
	    	]
	    	],
	    'columns'=>[
	        'last_accessed','_id'
	    ],
	    'columnDefs'=>[
			'1'=>['render'=>"'<a href=\"session_data.php?_id='+data+'\" class=\"button\"><span class=\"mif-eye\"></span></a><a href=\"javascript:removeid(\\''+data+'\\');\" class=\"button\"><span class=\"mif-cross\"></span></a>'"],// data $row[0]
		]
	]);
	
	
?>
<div class="container p-5">
	<div class="bg-cyan fg-white p-3"><h4><?=$nframework->language['user']?></h4></div>
	<div class="bg-white p-3">
	<?=secureform()?>
		<div class="grid">
			<div class="row">
				<div class="cell col"><?=$username?></div>
			</div>
			<div class="row">
				<div class="cell col"><?=$permisos?></div>
			</div>
			<?
			if(file_exists(include $_SERVER['DOCUMENT_ROOT'].'/admins/users/user.ui.php')){
				include $_SERVER['DOCUMENT_ROOT'].'/admins/users/user.ui.php';
			}
			?>
			<div class="row">
				<div class="cell-md-3 col offset-md-5"><a href="#" onclick="dialogpass_open();" class="btn btn-primary button primary w-100" data-toggle="modal" data-target="#demoDialog1" ><span class="mif-lock"></span>&nbsp;<?=$nframework->language['changepassword']?></a></div>
				<div class="cell-md-2 col"><a href="./" class="button primary btn btn-primary w-100"><span class="mif-exit"></span>&nbsp;<?=$nframework->language['close']?></a></div>
				<div class="cell-md-2 col"><button class="button btn btn-success secureop success w-100" value="save"><span class="mif-floppy-disk"></span>&nbsp;<?=$nframework->language['save']?></button></div>
			</div>
		</div>
	</form>
	<?=$datatable?>
	</div>
	<dialog id="dialogPass">
	    <div class="dialog-title"><?=$nframework->language['changepassword']?></div>
	    <div class="dialog-content">
	        <?=$password?>
	    </div>
	    <div class="dialog-actions">
	    	<button class="button success btn btn-success" onclick="dialogpass_close();"><?=$nframework->language['buttons']['cancel']?></button>
	        <button class="button primary btn btn-primary" onclick="cambiar();"><?=$nframework->language['buttons']['ok']?></button>
	    </div>
	</dialog>
</div>
<script>
	const dialogpass = document.querySelector("#dialogPass");
	function dialogpass_open(){
		dialogpass.showModal();
	}
	function dialogpass_close(){
		dialogpass.close();
	}
	
	function cambiar(){
		$.ajax({
			url: 'user.php?_id=<?=$dataset->_id?>',
			method: 'post',
			data: {
				op: 'password',
				password:$('#password').val()
			},
			success: function(respuesta){
				nAjaxFormDone(respuesta);
				dialogpass_close();
			},
			error: function() {
		        console.log("No se ha podido obtener la información");
		        dialogpass_close();
		    }
		});
		
	}
</script>
<?}?>