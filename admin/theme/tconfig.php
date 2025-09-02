<?
$developermode=true;
require '../common2.php';
	$dataset=new dataset([
    'collection'=>$m->{$config['sitedb']}->configs,
    '_id'=>'theme',
    'simpleid'=>true,
    'nameprefix'=>'data'
]);
$themeconfs=[];
require $_SERVER['DOCUMENT_ROOT'].'/nframework/templates/basic/theme.php';

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
	
?>
<div class="container p-5">
	<div class="bg-cyan fg-white p-3"><h4>Site config</h4></div>
	<div class="bg-white p-3">
	<?=secureform()?>
		<div class="grid">
			<?foreach($themeconfs as $themeconf){?>
			<div class="row">
				<div class="cell"><?=$themeconf?></div>
			</div>
			<?}?>
			<div class="row">
				<div class="cell-md-2 offset-md-8"><a href="./" class="button primary w-100"><span class="mif-exit"></span>&nbsp;Cerrar</a></div>
				<div class="cell-md-2"><button class="button secureop success w-100" value="save"><span class="mif-floppy-disk"></span>&nbsp;Guardar</button></div>
			</div>
		</div>
		
	</form>
	</div>
</div>
<?}?>