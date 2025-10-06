<?
require '../common2.php';

$dataset=new dataset(
    [
    'collection'=>$m->{$config['sitedb']}->configs,
    '_id'=>'site',
    'simpleid'=>true,
    'nameprefix'=>'data']
);

$google_oauth_client_id = new inputText([
	'dataset'=>&$dataset,
	'field'=>'google_oauth_client_id',
	'caption'=>'client_id:'
	]);
$google_oauth_client_secret =new inputText([
	'dataset'=>&$dataset,
	'field'=>'google_oauth_client_secret',
	'caption'=>'client_secret:'
	]);
$google_oauth_client_enable = new inputcheckbox([
	'dataset'=>&$dataset,
	'field'=>'google_oauth_client_enable',
	'caption'=>$nframework->language['enable']
	]);

$facebook_oauth_client_id = new inputText([
	'dataset'=>&$dataset,
	'field'=>'facebook_oauth_client_id',
	'caption'=>'client_id:'
	]);
$facebook_oauth_client_secret =new inputText([
	'dataset'=>&$dataset,
	'field'=>'facebook_oauth_client_secret',
	'caption'=>'client_secret:'
	]);
$facebook_oauth_client_enable = new inputcheckbox([
	'dataset'=>&$dataset,
	'field'=>'facebook_oauth_client_enable',
	'caption'=>$nframework->language['enable']
	]);

$microsoft_oauth_client_id = new inputText([
	'dataset'=>&$dataset,
	'field'=>'microsoft_oauth_client_id',
	'caption'=>'client_id:'
	]);
$microsoft_oauth_client_secret =new inputText([
	'dataset'=>&$dataset,
	'field'=>'microsoft_oauth_client_secret',
	'caption'=>'client_secret:'
	]);
$microsoft_oauth_client_enable = new inputcheckbox([
	'dataset'=>&$dataset,
	'field'=>'microsoft_oauth_client_enable',
	'caption'=>$nframework->language['enable']
	]);


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
	<div class="mt-4 mb-4">
		<h1 class="text-weight-10 text-center gradient gr-text-blue">OAuths2.0</h1>
	</div>
	<div class="box shadow-large-extra p-5">
	<?=secureform()?>
		<div class="grid">
			<div class="row">
				<div class="cell box-title">Google</div>
			</div>
			<div class="row">
				<div class="cell-md-2"><?=$google_oauth_client_enable?></div>
				<div class="cell-md-5"><?=$google_oauth_client_id?></div>
				<div class="cell-md-5"><?=$google_oauth_client_secret?></div>
			</div>
			<div class="row">
				<div class="cell box-title">Facebook</div>
			</div>
			<div class="row">
				<div class="cell-md-2"><?=$facebook_oauth_client_enable?></div>
				<div class="cell-md-5"><?=$facebook_oauth_client_id?></div>
				<div class="cell-md-5"><?=$facebook_oauth_client_secret?></div>
			</div>
			<div class="row">
				<div class="cell box-title">Microsoft 
				<a class="button" target="_blank" href="https://portal.azure.com/#view/Microsoft_AAD_RegisteredApps/ApplicationsListBlade">
					<span class="mif-link"></span>
				</a>
				</div>
			</div>
			<div class="row">
				<div class="cell-md-2"><?=$microsoft_oauth_client_enable?></div>
				<div class="cell-md-5"><?=$microsoft_oauth_client_id?></div>
				<div class="cell-md-5"><?=$microsoft_oauth_client_secret?></div>
			</div>
			<div class="row justify-content-md-right">
				<div class="cell-md-2 offset-md-8"><a href="./" class="btn btn-primary button primary w-100"><span class="mif-exit"></span>&nbsp;<?=$nframework->language['close']?></a></div>
				<div class="cell-md-2"><button class="button btn btn-success secureop success w-100" value="save"><span class="mif-floppy-disk"></span>&nbsp;<?=$nframework->language['save']?></button></div>
			</div>
		</div>
		
	</form>
	</div>
</div>
<?}?>