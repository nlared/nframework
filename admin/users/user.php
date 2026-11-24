<?php
if (empty($_GET['_id'])) {
	$newid = new MongoDB\BSON\ObjectID();
	header('Location: ?_id=' . $newid);
	exit();
}
require '../common2.php';
$dataset = new dataset(
	[
		'collection' => $m->{$config['sitedb']}->users,
		'_id' => $_GET['_id'],
		'simpleid' => false,
		'nameprefix' => 'data'
	]
);

$password = new inputText(['id' => 'password', 'name' => "password", 'caption' => $nframework->language['password'] . ':', 'required' => true]);
$username = new inputText(['dataset' => &$dataset, 'field' => 'username', 'caption' => $nframework->language['username'] . ':', 'required' => true]);

$disabled = new inputCheckbox(['dataset' => &$dataset, 'field' => 'disabled', 'caption' => $nframework->language['disabled'] . ':']);
$twofarequired = new inputCheckbox(['dataset' => &$dataset, 'field' => 'twofa_required', 'caption' => 'Requerir la verificación en dos pasos (2FA) a este usuario']);
// Estado del 2FA: el secreto nunca se muestra ni viaja al navegador.
$twofaUserId = is_string($_GET['_id']) && isValidObjectId($_GET['_id']) ? toMongoId($_GET['_id']) : null;
$twofaActive = $twofaUserId !== null && !empty($m->{$config['sitedb']}->users->findOne(
	['_id' => $twofaUserId],
	['projection' => ['twofa_secret' => 1]]
)['twofa_secret'] ?? null);

if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/admins/users/user.php')) {
	include $_SERVER['DOCUMENT_ROOT'] . '/admins/users/user.php';
}
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
	}
	if ($_POST['op'] == 'twofareset') {
		// El usuario pierde su teléfono: se borra el secreto y, si el 2FA es obligatorio, lo activa de nuevo al entrar.
		if (!csrfValidate()) {
			$result = ['error' => 'Formulario caducado, recargue la página.'];
		} elseif ($twofaUserId === null) {
			$result = ['error' => 'Usuario inválido.'];
		} else {
			$m->{$config['sitedb']}->users->updateOne(['_id' => $twofaUserId], ['$unset' => ['twofa_secret' => '']]);
			$result = ['error' => false, 'ids' => ['twofaStatus' => '<span class="tag">No activada</span>']];
		}
	}
	if ($_POST['op'] == 'password') {
		$dataset->password = nfPasswordHash((string) $_POST['password']);
		$result = [
			'error' => '',
			'js' => 'alert("Contraseña cambiada");'
		];
	}
} else {
	$nframework->usecommon = true;

	$datatable = new Table();
	$datatable->Ajax([
		'id' => 'sessions',
		'db' => $config['sitedb'],
		'collection' => 'sessions',
		'header' => '<th>Last accesed</th><th>id</th>',
		'pipeline' => [
			[
				'$match' => [
					'_id' => ['$in' => $dataset->sessions]
				]
			],
			[
				'$addFields' => [
					'last_accessed' => ['$toString' => '$last_accessed']
				]
			]
		],
		'columns' => [
			'last_accessed',
			'_id'
		],
		'columnDefs' => [
			'1' => ['render' => "'<a href=\"session_data.php?_id='+data+'\" class=\"button\"><span class=\"mif-eye\"></span></a><a href=\"javascript:removeid(\\''+data+'\\');\" class=\"button\"><span class=\"mif-cross\"></span></a>'"], // data $row[0]
		]
	]);


?>
	<div class="container p-5">
		<div class="box shadow-large-extra p-5">
			<div class="box-title mb-4"><?= $nframework->language['user'] ?></div>
			<?= secureform() ?>
			<div class="grid">
				<div class="row">
					<div class="cell"><?= $username ?></div>
				</div>
				<div class="row">
					<div class="cell"><?= $permisos ?></div>
				</div>
				<?php
				if (file_exists(include $_SERVER['DOCUMENT_ROOT'] . '/admins/users/user.ui.php')) {
					include $_SERVER['DOCUMENT_ROOT'] . '/admins/users/user.ui.php';
				}
				?>
				<div class="row">
					<div class="cell-md-6"><?= $twofarequired ?>
						<small class="d-block fg-gray mt-1">Hasta activarla solo podrá abrir <code>/account/twofa-setup</code> o salir.<?= !empty($config['twofa_required']) ? ' El sitio ya la exige a todos (Admin → Sitio).' : '' ?></small>
					</div>
					<div class="cell-md-6">
						2FA: <span id="twofaStatus"><?= $twofaActive ? '<span class="tag success">Activa</span>' : '<span class="tag">No activada</span>' ?></span>
						<?php if ($twofaActive) { ?>
							<button class="button secureop warning ml-2" value="twofareset" onclick="return confirm('¿Restablecer la verificación en dos pasos de este usuario? Tendrá que volver a escanear el código QR.');"><span class="mif-key"></span>&nbsp;Restablecer 2FA</button>
						<?php } ?>
					</div>
				</div>
				<div class="row">
					<div class="cell"><?= $disabled ?></div>
					<div class="cell-md-3"><a href="#" onclick="dialogpass_open();" class="btn btn-primary button primary w-100" data-toggle="modal" data-target="#demoDialog1"><span class="mif-lock"></span>&nbsp;<?= $nframework->language['changepassword'] ?></a></div>
					<div class="cell-md-2"><a href="./" class="button primary btn btn-primary w-100"><span class="mif-exit"></span>&nbsp;<?= $nframework->language['close'] ?></a></div>
					<div class="cell-md-2"><button class="button btn btn-success secureop success w-100" value="save"><span class="mif-floppy-disk"></span>&nbsp;<?= $nframework->language['save'] ?></button></div>
				</div>
			</div>
			</form>
			<?= $datatable ?>
		</div>
	</div>
	<dialog id="dialogPass">
		<div class="dialog-title"><?= $nframework->language['changepassword'] ?></div>
		<div class="dialog-content">
			<?= $password ?>
		</div>
		<div class="dialog-actions">
			<button class="button success btn btn-success" onclick="dialogpass_close();"><?= $nframework->language['buttons']['cancel'] ?></button>
			<button class="button primary btn btn-primary" onclick="cambiar();"><?= $nframework->language['buttons']['ok'] ?></button>
		</div>
	</dialog>
	<style>
		#dialogPass {
			width: min(600px, calc(100vw - 2rem));

		}
	</style>
	<script>
		< script >
			const dialogpass = document.querySelector("#dialogPass");

		function dialogpass_open() {
			dialogpass.showModal();
		}

		function dialogpass_close() {
			dialogpass.close();
		}

		function cambiar() {
			$.ajax({

				url: 'user.php?_id=<?= $dataset->_id ?>',
				method: 'post',
				data: {
					op: 'password',
					password: $('#password').val()
				}

				,
				success: function(respuesta) {
						nAjaxFormDone(respuesta);
						dialogpass_close();
					}

					,
				error: function() {
					console.log("No se ha podido obtener la información");
					dialogpass_close();
				}
			});
		}
	</script><?php } ?>