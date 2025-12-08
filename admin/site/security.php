<?
require '../common2.php';
$dataset = new dataset(
	[
		'collection' => $m->{$config['sitedb']}->configs,
		'_id' => 'site',
		'simpleid' => true,
		'nameprefix' => 'data'
	]
);

$blockips = new inputCheckbox([
	'dataset' => &$dataset,
	'field' => 'blockips',
	'caption' => $nframework->language['blockips'] . ':'
]);


$dialogIps = new Dialog([
	'title' => 'Blocked IP',
]);
$arrayIps = new embededArray([
	'dataset' => &$dataset,
	'field' => 'security_ip_blacklist',
	'containerid' => 'list_ips',
	'dialogid' => 'dialogs_0',
	'template' => <<<T
	{% if items|length > 0 %}
    	<table class="table">
        {% for key,item in items %}
            <tr><td>{{ item.ip|e }}</td><td>{{ item.end|e }}</td><td>
            		<div class="button primary" onclick="javascript:{{function_get}}('{{key}}')"><span class="mif-pencil"></span></div>
					<div class="button alert" onclick="javascript:{{function_delete}}('{{key}}')"><span class="mif-cross"></span></div>
				</td>
            </tr>
        {% endfor %}
    	</table>
	{% else %}
	no hay
	{% endif %}
T
]);

$blockedips_ip = new inputtext(['nfembeded' => &$arrayIps, 'field' => 'ip', 'caption' => 'IP:']);
$blockedips_until = new inputText(['nfembeded' => &$arrayIps, 'field' => 'end', 'caption' => 'Until:']);
$dialogIps->content = <<<FORM
	<div class="grid">
		<div class="row">
			<div class="cell">
				$blockedips_ip
			</div>	
		</div>
		<div class="row">
			<div class="cell">
				$blockedips_until
			</div>	
		</div>
	</div>
FORM;

echo $dialogIps;
echo $arrayIps;


$datatable=new Table();
$datatable->Ajax([
    'id'=>'testid',
    'db'=>$config['sitedb'],
    'collection'=>'nfsecurityrules',
    'header'=>'<th>Name</th><th>Rules</th><th>id</th>',
    
    'columns'=>[
        'name','rule','_id'
    ],
    'columnDefs'=>[
		'2'=>['render'=>"'<a href=\"rule.php?_id='+data+'\" class=\"button primary\"><span class=\"mif-pencil\"></span></a>'+
		
		'<a href=\"javascript:removeid(\\''+data+'\\');\" class=\"button alert\"><span class=\"mif-cross\"></span></a>'"],// data $row[0]
	]
]);


if ($nframework->isAjax()) {
	if ($_POST['op']=='delete') {
		$m->{$config['sitedb']}->nfsecurityrules->deleteOne(['_id'=>tomongoid($_POST['_id'])]);
	}
}else{
	$nframework->usecommon=true;
	$javas->addjs("
	function removeid(id){
		Swal.fire({
			title: 'Estas seguro?',
			text: 'No podras deshacer esto!',
			icon: 'warning',
			showCancelButton: true,
			confirmButtonColor: '#3085d6',
			cancelButtonColor: '#d33',
			confirmButtonText: 'Si, borrar!'
		}).then((result) => {
			if (result.isConfirmed) {
				$.ajax({
					url: \"$_SERVER[PHP_SELF]\",
					method: 'post',
					cache: false, 
					data:{
						op: 'delete',
						_id: id
					}
				}).done(function() {
					datatable=$('#testid').DataTable();
					datatable.clearPipeline();
					datatable.draw();
					Swal.fire(
	    				'Borrado!',
		    			'El registro ha sido eliminado.',
	    				'success'
	    			)
				});
			}
		})
	}
	");

?>

<style>
	#dialogs_0{
		width: 800px;	
	}
	
</style>
<div class="container p-5">
	<div class="box">
		<div class="box-title">Security</div>	
		<?=secureform()?>
			<div class="grid">
				<div class="row bg-cyan fg-white p-3">
					<div class="cell">Blockeds IPS</div>
				</div>
				<div class="row">
					<div class="cell"><?=$blockips?></div>
				</div>
				<div class="row">
					<div class="cell" id="list_ips"></div>
				</div>
				<div class="row">
					<div class="cell">
						<div class="button" onclick="<?=$arrayIps->function_new()?>"><span class="mif-plus">Agregar</span></div>
					</div>
				</div>
				<div class="row">
					<div class="cell"> 
						<?=$datatable?>
					</div>
				</div>
				<div class="row ">
					<div class="cell-md-2"><a href="rule.php" class="btn btn-primary button primary w-100"><span class="mif-plus"></span>&nbsp;<?=$nframework->language['new']?></a></div>
					<div class="cell-md-2 offset-md-6"><a href="./" class="btn btn-primary button primary w-100"><span class="mif-exit"></span>&nbsp;<?=$nframework->language['close']?></a></div>
					<div class="cell-md-2"><button class="button btn btn-success secureop success w-100" value="save"><span class="mif-floppy-disk"></span>&nbsp;<?=$nframework->language['save']?></button></div>
				</div>
			</div>				
		</form>
	</div>
</div>
<?}?>