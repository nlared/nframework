<?php
if (empty($_GET['_id'])) {
    $newid=new MongoDB\BSON\ObjectID();
    header('Location: ?_id='.$newid);
    exit();
}
require '../common2.php';
$dataset=new dataset(
    [
    'collection'=>$m->{$config['sitedb']}->usersgroups,
    '_id'=>$_GET['_id'],
    'simpleid'=>false,
    'nameprefix'=>'data']
);
$noobfuscate=true;

$title=new inputtext(['dataset'=>&$dataset,'field'=>'name','caption'=>'Name:',]);
$description=new inputtext(['dataset'=>&$dataset,'field'=>'description','caption'=>'Description:',]);



if ($nframework->isAjax()) {
	$nframework->usecommon=false;
	if ($_POST['op']=='save') {
        $session = $m->startSession();
        $session->startTransaction();
        
        $vals=explode(',',$_POST['listain']);
        $tosave=[];
        foreach($m->{$config['sitedb']}->users->find(['username'=>['$in'=>$vals]]) as $d){
        	$tosave[]=$d->_id;
        }
        
        
        try {
        	$dataset->users=$tosave;
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
	$list=(array)$dataset->users;
	$developermode=true;
	$listin=[];
	$listout=[];
	foreach($m->{$config['sitedb']}->users->find() as $u){
		if(in_array((string)$u->_id,$list)){
			$listin[]=$u->username;
		}else{
			$listout[]=$u->username;
		}
	}
	
	$javas->addjs('
	$("#listain").on("dragenter", function(e){
        console.log(e.detail);
    })
	$("#listaout").on("dragenter", function(e){
        console.log(e.detail);
    })
	console.log("read");
	','ready');
?>
<div class="container p-5">
	<div class="bg-cyan fg-white p-3"><h4>Group</h4></div>
	<div class="bg-white p-3">
	<?=secureform()?>
		<div class="grid">
			<div class="row">
				<div class="cell"><?=$title?></div>
			</div>
			<div class="row">
				<div class="cell"><?=$description?></div>
			</div>
			<div class="row">
				<div class="cell-md-6">
					In:<br>
					<ul data-role="drag-items" data-on-drag-drop-item="prueba" id="listain" class="border group-list list-group">
					    <?if(count($listin))echo '<li class="list-group-item">'.implode('</li><li>',$listin ).'</li>' ?>
					</ul>
				</div>
				<div class="cell-md-6">
					Out:<br>
					<ul data-role="drag-items" data-on-drag-drop-item="prueba" id="listaout" class="border group-list list-group">
					    <?if(count($listout))echo '<li class="list-group-item">'.implode('</li><li>',$listout ).'</li>' ?>
					</ul>
				</div>
			</div>
			<div class="row">
				<div class="cell-md-2 offset-md-8"><a href="./" class="button primary btn btn-primary w-100"><span class="mif-exit"></span>&nbsp;Cerrar</a></div>
				<div class="cell-md-2"><button class="button secureop success btn btn-success w-100" value="save"><span class="mif-floppy-disk"></span>&nbsp;Guardar</button></div>
			</div>
		</div>
		<input name="listain" id="listastr" type="hidden" value="<?=implode(',',$listin)?>">
	</form>
	</div>
</div>
<script>
	var listain=<?=json_encode($listin)?>;
	var listaout=<?=json_encode($listin)?>;
	function prueba(ele, pos){
		var values=[];
		list=$('#listain').find('li').each(function(){
            // cache jquery var
            var current = $(this);
            // check if our current li has children (sub elements)
            // if it does, skip it
            // ps, you can work with this by seeing if the first child
            // is a UL with blank inside and odd your custom BLANK text
            //if(current.children().size() > 0) {return true;}
            // add current text to our current phrase
        	values.push(current.text());
		});
		$('#listastr').val(values.join(","));
	}
</script>
<?}?>