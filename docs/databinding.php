<?php
if (empty($_GET['_id'])) {
    $newid=new MongoDB\BSON\ObjectID();
    header('Location: ?_id='.$newid);
    exit();
}
$antixss=true;
require 'common.php';
$dataset=new dataset(
    [
    'collection'=>$m->nframework->exampledata,
    '_id'=>$_GET['_id'],
    'simpleid'=>false,
    'nameprefix'=>'data']
);
$texto=new inputText(['dataset'=>& $dataset,'field'=>'text']);
$texto2=new inputText(['dataset'=>& $dataset,'field'=>'array.text2']);
$number=new inputnumber(['dataset'=>& $dataset,'field'=>'number']);
$date=new inputdate(['dataset'=>& $dataset,'field'=>'date']);

$checkbox=new inputCheckbox(['dataset'=>& $dataset,'field'=>'checkbox']);
if ($_POST['op']=='Guardar') {
    $session = $m->startSession();
    $session->startTransaction();
    
    
    try {
        $dataset->other=date();
        $dataset->save();
    
        $session->commitTransaction();
    } catch (Exception $e) {
        $session->abortTransaction();
    }
}
?>
<div class="container">
	<form method="POST">
		<div class="grid">
			<div class="row">
				<div class="cell col">Text</div>
				<div class="cell col"><?=$texto?></div>
			</div>
			<div class="row">
				<div class="cell col">Text</div>
				<div class="cell col"><?=$texto2?></div>
			</div>
			<div class="row">
				<div class="cell col">Number</div>
				<div class="cell col"><?=$number?></div>
			</div>
			<div class="row">
				<div class="cell col">Number</div>
				<div class="cell col"><?=$date?></div>
			</div>
			<div class="row">
				<div class="cell col">Checkbox</div>
				<div class="cell col"><?=$checkbox?></div>
			</div>
		</div>
		<input name="op" class="button primary btn-primary" value="Guardar" type="submit">
	</form>
</div>
<pre><code class="html">
<?=tocode(__file__) ?>
}?>
</code></pre>