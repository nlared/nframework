<?
if (empty($_GET['_id'])) {
    $newid=new MongoDB\BSON\ObjectID();
    header('Location: ?_id='.$newid);
    exit();
}

require '../common2.php';

$nframework->usecommon=true;
$pages=['/'=>'Home'];
foreach($m->{$config['sitedb']}->pages->find() as $d){
	$pages[$d['path']]=$d['title'];
}

$pagess=new select(['options'=>$pages,'id'=>'page','caption'=>'Link:']);
$dataset=new dataset(
    [
    'collection'=>$m->{$config['sitedb']}->menus,
    '_id'=>$_GET['_id'],
    'simpleid'=>false,
    'nameprefix'=>'data']
);


$name=new inputtext(['dataset'=>&$dataset,'field'=>'name','caption'=>'Name:',]);
$caption=new inputtext(['name'=>'Caption','caption'=>'Caption:']);
$code=new textarea(['dataset'=>&$dataset,'field'=>'code','caption'=>'Code:',]);


if ($nframework->isAjax()) {
	$nframework->usecommon=false;
	if ($_POST['op']=='save') {
        $session = $m->startSession();
        $session->startTransaction();
        $dataset->lastmodification=new MongoDB\BSON\UTCDateTime();
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
	
?>
<div class="container p-5">
	<div class="bg-cyan fg-white p-3"><h4>Menu</h4></div>
	<div class="bg-white p-3">
		<?=secureform()?>
		<div class="grid">
			<div class="row">
				<div class="cell"><?=$name?></div>
			</div>
			<div class="row">
				<div class="cell">
					<ul data-role="treeview"  class="h-menu" id="tv_1"><?=$code->value?></ul>
				</div>
			</div>
			<div class="row">
				<div class="cell">
			<?=$code?>
				</div>
				
				<div class="cell">
					<?=$caption?>
					<?=$pagess?>
					
<button class="button" onclick="
	var select = Metro.getPlugin('#page', 'select');
	var txt= $('#page option:selected').text();
	console.log(select.getSelected());
	
    Metro.getPlugin('#tv_1','treeview').addTo(null, {
    	html: '<spam class=caption href=\''+select.val()+'\'>'+txt+'</spam>'
    });
    $('#data_code').val($('#tv_1').html());
">Add node</button>

<button class="button" onclick="
    Metro.getPlugin('#tv_1','treeview').addTo($('#tv_1').find('.current'), {
        caption: 'New node'
    })
">Add subnode</button>

<button class="button" onclick="
    Metro.getPlugin('#tv_1','treeview').addTo($('#tv_1').find('.current'), {
        html: '<input data-role=checkbox data-caption=Checkbox>'
    })
">Add checkbox</button>

<button class="button" onclick="
    Metro.getPlugin('#tv_1','treeview').insertBefore($('#tv_1').find('.current'), {
        caption: 'Before node'
    })
">Insert before</button>

<button class="button" onclick="
    Metro.getPlugin('#tv_1','treeview').insertAfter($('#tv_1').find('.current'), {
        caption: 'After node'
    })
">Insert after</button>

<button class="button" onclick="
    Metro.getPlugin('#tv_1','treeview').del($('#tv_1').find('.current'))
">Delete</button>

<button class="button" onclick="
    Metro.getPlugin('#tv_1','treeview').clean($('#tv_1').find('.current'))
">Clear</button>

</div>
			</div>
			<div class="row">
				<div class="cell-md-2 offset-md-8"><a href="./" class="button primary w-100"><span class="mif-exit"></span>&nbsp;Cerrar</a></div>
				<div class="cell-md-2"><button class="button secureop success w-100" value="save"><span class="mif-floppy-disk"></span>&nbsp;Guardar</button></div>
			</div>
		</div>
<?}?>