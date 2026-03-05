<?
require '../common2.php';
require 'timezonelist.php';
$dataset = new dataset(
	[
		'collection' => $m->{$config['sitedb']}->configs,
		'_id' => 'site',
		'simpleid' => true,
		'nameprefix' => 'data'
	]
);

$themesdir = scandir('../../themes/');
$developermode = true;

foreach ($themesdir as $themedir) {
	if ($themedir != '.' && $themedir != '..') {
		$themes[$themedir] = $themedir;
	}
}


$title = new inputText(['dataset' => &$dataset, 'field' => 'title', 'caption' => $nframework->language['title'] . ':', 'required' => true]);
$shortname = new inputText(['dataset' => &$dataset, 'field' => 'shortname', 'caption' => $nframework->language['shortname'] . ':', 'required' => true]);
$tagline = new inputText(['dataset' => &$dataset, 'field' => 'tagline', 'caption' => 'Tagline:']);
$image = new inputText(['dataset' => &$dataset, 'field' => 'image', 'caption' => 'Image:']);
$description = new textarea(['dataset' => &$dataset, 'field' => 'description', 'caption' => $nframework->language['description'] . ':', 'required' => true]);
$timezone = new select(['dataset' => &$dataset, 'field' => 'timezone', 'caption' => $nframework->language['timezone'] . ':', 'options' => $timezones]);

$theme = new select(['dataset' => &$dataset, 'field' => 'theme', 'caption' => $nframework->language['theme'] . ':', 'options' => $themes]);
$homepagetype = new inputradios(['dataset' => &$dataset, 'field' => 'homepagetype', 'caption' => $nframework->language['homepagetype'] . ':<br>', 'options' => [
	'page' => 'Page',
	'blog' => 'Blog'
]]);
$email = new inputText(['dataset' => &$dataset, 'field' => 'email', 'caption' => $nframework->language['webmasteremail'] . ':']);

$logo = new inputfile([
	'id' => 'logo',
	'name' => 'logo',
	'dir' => $_SERVER['DOCUMENT_ROOT'] . '/img/nf/',
	'path' => $_SERVER['DOCUMENT_ROOT'] . '/img/nf/logo.png',
	'accept' => '.png',
	'drop' => false,
	'onDone' => <<<js
	if (data.files.length==1){
		$("#img-container").html('<img width="256" src="/images/config/256/logo.png?time='+Date.now()+'">');	
	}
js
]);



$google_site_verification = new inputText(['dataset' => &$dataset, 'field' => 'google-site-verification', 'caption' => 'google-site-verification:']);
$google_captcha_key = new inputText(['dataset' => &$dataset, 'field' => 'google-captcha-key', 'caption' => 'google-captcha-key:']);
$google_captcha_secret = new inputText(['dataset' => &$dataset, 'field' => 'google-captcha-secret', 'caption' => 'google-captcha-secret:']);
$google_maps_api = new inputText(['dataset' => &$dataset, 'field' => 'google-maps-api', 'caption' => 'google-maps-api:']);
//https://console.cloud.google.com/google/maps-apis/credentials?

$canregister = new inputcheckbox(['dataset' => &$dataset, 'field' => 'canregister', 'caption' => $nframework->language['canregister'] . ':']);
$passwordmask = new inputText(['dataset' => &$dataset, 'field' => 'passwordmask', 'caption' => $nframework->language['passwordmask'] . ':', 'default' => '/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,}$/', 'required' => true]);


$mailhost = new inputText(['dataset' => &$dataset, 'field' => 'smtp.host', 'caption' => $nframework->language['host'] . ':']);
$mailusername = new inputText(['dataset' => &$dataset, 'field' => 'smtp.username', 'caption' => $nframework->language['username'] . ':']);
$mailpassword = new inputText(['dataset' => &$dataset, 'field' => 'smtp.password', 'caption' => $nframework->language['password'] . ':']);
$mailport = new inputNumber(['dataset' => &$dataset, 'field' => 'smtp.port', 'caption' => $nframework->language['port'] . ':']);
$mailsmtpauth = new inputcheckbox(['dataset' => &$dataset, 'field' => 'smtp.auth', 'caption' => $nframework->language['smtpauth'] . ':']);
$mailcrypt = new inputradios(['dataset' => &$dataset, 'field' => 'smtp.secure', 'caption' => $nframework->language['encrypt'] . ':', 'options' => [
	'ssl' => 'ssl',
	'tls' => 'tls'
]]);
$mailfromname = new inputText(['dataset' => &$dataset, 'field' => 'smtp.fromname', 'caption' => $nframework->language['fromname'] . ':']);
$mailfromemail = new inputText(['dataset' => &$dataset, 'field' => 'smtp.fromemail', 'caption' => $nframework->language['fromemail'] . ':']);



$letsencryptemail = new inputText(['dataset' => &$dataset, 'field' => 'letsencrypt_email', 'caption' => $nframework->language['email'] . ':']);
$letsencryptuse = new inputcheckbox(['dataset' => &$dataset, 'field' => 'letsencrypt_use', 'caption' => $nframework->language['useletsencrypt'] . ':']);

$wordtopdfconvertion = new select(['dataset' => &$dataset, 'field' => 'word_pdf_converter', 'caption' => 'Word to PDF conversion:', 'options' => [
	'' => 'DOMPDF',
	'unoconv' => 'unoconv',
	'unoconv-server' => 'unoconv server'
]]);

$exceltopdfconvertion = new select(['dataset' => &$dataset, 'field' => 'excel_pdf_converter', 'caption' => 'Excel to PDF conversion:', 'options' => [
	'' => 'DOMPDF',
	'unoconv' => 'unoconv',
	'unoconv-server' => 'unoconv server'
]]);

if ($nframework->isAjax()) {
	if ($_POST['op'] == 'save') {
		$session = $m->startSession();
		$session->startTransaction();
		try {
			$result = [
				'error' => $dataset->save(),
			];
			$session->commitTransaction();
		} catch (Exception $e) {
			$session->abortTransaction();
			$result = [
				'error' => $e->getMessage()
			];
		}


		if ($dataset->letsencrypt_use == 'on') {
			$client = new Api($dataset->letsencrypt_email, __DIR__ . '/__account');
			if (!$client->account()->exists()) {
				$account = $client->account()->create();
			} else {
				$account = $client->account()->get();
			}
			if ($renovar) {
				$order = $client->order()->new($account, ['example.com']);
				$order = $client->order()->get($order->id);
				$validationStatus = $client->domainValidation()->status($order);
				$validationData = $client->domainValidation()->getValidationData($validationStatus, AuthorizationChallengeEnum::HTTP);
			}
		}
	}
} else {
	$nframework->usecommon = true;

?>
	<div class="container p-5">
		<div class="mt-4 mb-4">
			<h1 class="text-weight-10 text-center gradient gr-text-blue"><?= $nframework->language['siteconfig'] ?></h1>
		</div>
		<div class="box shadow-large-extra p-5">
			<?= secureform() ?>
			<div class="grid">
				<div class="row">
					<div class="cell-md-6"><?= $title ?></div>
					<div class="cell-md-6"><?= $shortname ?></div>
				</div>
				<div class="row">
					<div class="cell"><?= $tagline ?></div>
				</div>
				<div class="row">
					<div class="cell"><?= $description ?></div>
				</div>
				<div class="row">
					<div class="cell"><?= $logo ?>
						<div id="img-container">

						</div>
					</div>
				</div>
				<div class="row">
					<div class="cell">
						<table style="width:100%;">
							<tr>
								<td>body__background</td>
								<td><?= $color_root_body__background ?></td>
								<td><?= $color_dark_body__background ?></td>
							</tr>
							<tr>
								<td>body_color</td>
								<td><?= $color_root_body_color ?></td>
								<td><?= $color_dark_body_color ?></td>
							</tr>
							<tr>
								<td>border_color</td>
								<td><?= $color_root_border_color ?></td>
								<td><?= $color_dark_border_color ?></td>
							</tr>
							<tr>
								<td>link_color</td>
								<td><?= $color_root_link_color ?></td>
								<td><?= $color_dark_link_color ?></td>
							</tr>
							<tr>
								<td>link_color_hover</td>
								<td><?= $color_root_link_color_hover ?></td>
								<td><?= $color_dark_link_color_hover ?></td>
							</tr>
						</table>
					</div>
				</div>
				<div class="row">
					<div class="cell"><?= $email ?></div>
				</div>
				<div class="row">
					<div class="cell"><?= $timezone ?></div>
					<div class="cell"><?= $theme ?></div>
					<div class="cell"><?= $themeupload ?></div>
				</div>
				<div class="row">
					<div class="cell"><?= $passwordmask ?></div>
					<div class="cell"><?= $homepagetype ?></div>
					<div class="cell"><br><?= $canregister ?></div>
					<div class="cell"><br><?= $usebootstrap ?></div>
				</div>
			</div>

			<div class="row">
				<div class="cell box-title">Mail Config</div>
			</div>
			<div class="row">
				<div class="cell-md-6"><?= $mailhost ?></div>
				<div class="cell-md-6"><?= $mailport ?></div>
			</div>
			<div class="row">
				<div class="cell-md-6"><?= $mailusername ?></div>
				<div class="cell-md-6"><?= $mailpassword ?></div>
			</div>
			<div class="row">
				<div class="cell-md-6"><?= $mailfromemail ?></div>
				<div class="cell-md-6"><?= $mailfromname ?></div>
			</div>
			<div class="row">
				<div class="cell-md-6"><?= $mailcrypt ?></div>
				<div class="cell-md-6"><?= $mailsmtpauth ?></div>
			</div>
			<div class="row">
				<div class="cell box-title">Lets Encrypt</div>
			</div>
			<div class="row">
				<div class="cell-md-6"><?= $letsencryptuse ?></div>
				<div class="cell-md-6"><?= $letsencryptemail ?></div>
			</div>
			<div class="row">
				<div class="cell box-title">Google keys</div>
			</div>
			<div class="row">
				<div class="cell-md-6"><?= $google_site_verification ?></div>
				<div class="cell-md-6"><?= $google_maps_api ?></div>
			</div>
			<div class="row">
				<div class="cell-md-6"><?= $google_captcha_key ?></div>
				<div class="cell-md-6"><?= $google_captcha_secret ?></div>
			</div>

			<div class="row">
				<div class="cell-md-6"><?= $wordtopdfconvertion ?></div>
				<div class="cell-md-6"><?= $exceltopdfconvertion ?></div>
			</div>


			<div class="row justify-content-md-right">
				<div class="cell-md-2 offset-md-8"><a href="./" class="btn btn-primary button primary w-100"><span class="mif-exit"></span>&nbsp;<?= $nframework->language['close'] ?></a></div>
				<div class="cell-md-2"><button class="button btn btn-success secureop success w-100" value="save"><span class="mif-floppy-disk"></span>&nbsp;<?= $nframework->language['save'] ?></button></div>
			</div>
		</div>

		</form>
	</div>
	</div>
<? } ?>