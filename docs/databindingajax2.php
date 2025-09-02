<?php
if ($_GET['_id']=='') {
    $newid=new MongoDB\BSON\ObjectID();
    header('Location: ?_id='.$newid);
    exit();
}
$developermode=true;
require 'include.php';
$dataset=new dataset(
    [
    'collection'=>$m->{$config['sitedb']}->exampledata,
    '_id'=>$_GET['_id'],
    'simpleid'=>false,
    'nameprefix'=>'data']
);
$texto=new inputText(['dataset'=>$dataset,'field'=>'text','caption'=>'Texto:','required'=>true]);
$number=new inputnumber(['dataset'=>$dataset,'field'=>'number','caption'=>'Number:']);
$inputDate=new inputDate(['dataset'=>$dataset,'field'=>'date','caption'=>'Date:','format'=>'%d/%m/%Y']);
$checkbox=new inputCheckbox(['dataset'=>$dataset,'field'=>'checkbox','caption'=>'Checkbox']);
//$rte=new inputRte(['dataset'=>$dataset,'field'=>'rte','caption'=>'Rte']);
//if ($nframework['isAjax']) {
    if ($_POST['op']=='save') {
        $session = $m->startSession();
        $session->startTransaction();
        try {
            $dataset->other=date('Y-m-d');
            $result=[
                'error'=>$dataset->save(),
                'ids'=>[
                    'time'=>date('Y-m-d h:i:s'),
                    'test'=>uniqid(),
                ],
                'js'=>'alert("test")'
            ];
            $session->commitTransaction();
        } catch (Exception $e) {
            $session->abortTransaction();
            $result=[
            	'error'=>$e->getMessage()
        	];
        }
  //  }
}else{
?>
<?=secureform()?>
	<div class="grid">
		<div class="row">
			<div class="cell"><?=$texto?></div>
		</div>
		<div class="row">
			<div class="cell"><?=$number?></div>
		</div>
		<div class="row">
			<div class="cell"><?=$inputDate?></div>
		</div>
		<div class="row">
			<div class="cell"><?=$checkbox?></div>
		</div>
		<div class="row">
			<div class="cell"><?=$rte?></div>
		</div>
		<div class="row">
			<div class="cell">
				id time<div id="time"></div>
				id test<div id="test"></div>
			</div>
		</div>
	</div>
</form>
<?}?>