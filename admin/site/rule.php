<?
/*
$rule_field=new select(['nfembeded'=>&$arrayIps,'field'=>'field','caption'=>'Field:','options'=>[
	'Hostname',
	'IP',
	'Referer',
	'Method',
	'HTTPS',
	'User Agent',
	'X-Forwarded-For',
	'Header',
	'URI Full',
	'URI',
	'URI Path',
	'URI String'
]]);
$rule_operator=new inputtext(['nfembeded'=>&$arrayIps,'field'=>'ip','caption'=>'Operator:']);
$rule_value=new inputtext(['nfembeded'=>&$arrayIps,'field'=>'ip','caption'=>'Value:']);
*/

if (empty($_GET['_id'])) {
    $newid=new MongoDB\BSON\ObjectID();
    header('Location: ?_id='.$newid);
    exit();
}
require '../common2.php';
$dataset=new dataset(
    [
    'collection'=>$m->{$config['sitedb']}->nfsecurityrules,
    '_id'=>$_GET['_id'],
    'simpleid'=>false,
    'nameprefix'=>'data']
);
$noobfuscate=true;
$developermode=true;

$name=new inputText(['dataset'=>&$dataset,'field'=>'name','caption'=>'Name:','required'=>true]);
$rule=new textarea(['dataset'=>&$dataset,'field'=>'rule','caption'=>'Code:','readonly'=>true]);



$base=[];
$datatable=new Table();
$datatable->Ajax([
    'id'=>'resultado',
    'db'=>$config['sitedb'],
    'collection'=>'nfuristats',
    'header'=>'<th>Date</th><th>IP</th><th>Hostname</th><th>Path</th><th>Agent</th>',
    'pipeline'=>$base,
    'columns'=>[
        'created_at','ip','host','path','agent'
	]
]);



$tablef=new Tablef([
	//'excelFile'=>__DIR__.'/alumnos.xlsx',
	//'excelCell'=>'A2',
	'query'=>$dataset->rule,
	'codeid'=>'data_rule',
	'table'=>&$datatable,
	'filters'=>[
			['id'=>'host','field'=>'host','label'=>'Hostname','type'=>'string'],
			['id'=>'ip','field'=>'ip','label'=>'IP','type'=>'string'],
			['id'=>'referer','field'=>'referer','label'=>'Referer','type'=>'string'],
			['id'=>'method','field'=>'method','label'=>'Method','type'=>'string','input'=>'select','values'=>['HTTP','HTTPS']],
			['id'=>'agent','field'=>'agent','label'=>'User Agent','type'=>'string'],
			['id'=>'path','field'=>'path','label'=>'URI Path','type'=>'string'],
		]	
	]);
if ($nframework->isAjax()) {
	$nframework->usecommon=false;
	if ($_POST['op']=='save') {
        $session = $m->startSession();
        $session->startTransaction();
        $dataset->session=$session;
        try {
            $dataset->other=date('Y-m-d');
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
	
?>
<div class="container p-5">
	<div class="bg-cyan fg-white p-5"><h2>Rule</h2></div>
	<form action="excel.php">
	<div class="bg-white p-5">
		<?=$tablef?>
		<?=$datatable;?>
	</form>
	
	<?=secureform()?>
		<div class="grid">
			<div class="row">
				<div class="cell"><?=$name?></div>
			</div>
			<div class="row">
				<div class="cell"><?=$rule?></div>
			</div>
			<div class="row">
				<div class="cell-md-2 offset-md-8"><a href="security.php" class="button primary btn btn-primary w-100"><span class="mif-exit"></span>&nbsp;Cerrar</a></div>
				<div class="cell-md-2"><button class="button success btn btn-success secureop  w-100" value="save"><span class="mif-floppy-disk"></span>&nbsp;Guardar</button></div>
			</div>
		</div>
	</form>
	</div>
</div>

<?}?>