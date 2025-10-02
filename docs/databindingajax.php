<?php
if (empty($_GET['_id'])) {
	$newid = new MongoDB\BSON\ObjectID();
	header('Location: ?_id=' . $newid);
	exit();
}
require '../common2.php';
$dataset = new dataset(
	[
		'collection' => $m->{$config['sitedb']}->exampledata,
		'_id' => $_GET['_id'],
		'simpleid' => false,
		'nameprefix' => 'data'
	]
);
$noobfuscate = true;
$developermode = true;

$texto = new inputText(['dataset' => &$dataset, 'field' => 'text', 'caption' => 'Texto:', 'required' => true]);
$number = new inputnumber(['dataset' => &$dataset, 'field' => 'number', 'caption' => 'Number s:']);
$inputDate = new inputDate(['dataset' => &$dataset, 'field' => 'date', 'caption' => 'Date:', 'prepend' => 'prepend', 'rlequired' => true]);
$inputDateTime = new inputDatetime(['dataset' => &$dataset, 'field' => 'datetime', 'caption' => 'DateTime:']);

$checkbox = new inputCheckbox(['dataset' => &$dataset, 'field' => 'checkbox', 'caption' => 'Checkbox']);
$checkboxs = new inputCheckboxs([
	'dataset' => &$dataset,
	'field' => 'checkboxs',
	'caption' => 'Checkboxs',
	'options' => [
		'option1',
		'option2'
	]

]);
$checkboxs2 = new inputCheckboxs([
	'dataset' => &$dataset,
	'field' => 'checkboxs2',
	'caption' => 'Checkboxs',
	'options' => [
		'value1' => 'Option 1',
		'value2' => 'option 2'
	]
]);
$radios1 = new inputRadios([
	'dataset' => &$dataset,
	'field' => 'radios1',
	'caption' => 'Radios',
	'options' => [
		'value1' => 'Option 1',
		'value2' => 'option 2'
	]
]);
$textarea = new textarea(['dataset' => &$dataset, 'field' => 'textarea', 'caption' => 'textarea:', 'prepend' => 'prepend', 'rlequired' => true]);

//$rte=new inputRte(['dataset'=>$dataset,'field'=>'rte','caption'=>'Rte']);
//*/
if ($nframework->isAjax()) {
	if ($_POST['op'] == 'save') {
		try {
			//$dataset->other=date('Y-m-d');
			$result = [
				'error' => $dataset->save(),
				/*  'ids'=>[
                    'time'=>date('Y-m-d h:i:s'),
                    'test'=>uniqid(),
                ],
                'js'=>'alert("test")'*/
			];
		} catch (Exception $e) {
			$result = [
				'error' => $e->getMessage()
			];
		}
	}
} else {
	$nframework->usecommon = true;	
?>

<div class="container p-5">
		<div class="p-3 mb-3 ">
			<h4>Ajax Databinding</h4>
		</div>
		<div class="p-3">
			<?= secureform() ?>
			<div class="grid">
				<div class="row">
					<div class="cell"><?= $texto ?></div>
				</div>
				<div class="row">
					<div class="cell"><?= $number ?></div>
				</div>
				<div class="row">
					<div class="cell"><?= $inputDate ?></div>
				</div>
				<div class="row">
					<div class="cell"><?= $inputDateTime ?></div>
				</div>
				<div class="row">
					<div class="cell"><?= $checkbox ?></div>
				</div>
				<div class="row">
					<div class="cell"><?= $checkboxs ?></div>
				</div>
				<div class="row">
					<div class="cell"><?= $checkboxs2 ?></div>
				</div>
				<div class="row">
					<div class="cell"><?= $textarea ?></div>
				</div>

				<div class="row">
					<div class="cell">
						id time<div id="time"></div>
						id test<div id="test"></div>
					</div>
				</div>
				<div class="row">
					<div class="cell-md-2 offset-md-8"><a href="datatableajax.php" class="button primary btn btn-primary w-100"><span class="mif-exit"></span>&nbsp;Cerrar</a></div>
					<div class="cell-md-2"><button class="button success btn btn-success secureop  w-100" value="save"><span class="mif-floppy-disk"></span>&nbsp;Guardar</button></div>
				</div>
			</div>
		</form>


			<pre class="stay-on"><code class="language-plaintext">
<?= tocode(__file__) ?>
</code></pre>
		</div>
	</div>
<?php
} ?>