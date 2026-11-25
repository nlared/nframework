<?php
require '../common2.php';
if (!$user->in('admins')) {
	//exit();
}
$datatable = new Table();
$datatable->Ajax([
	'id' => 'users',
	'db' => $config['sitedb'],
	'collection' => 'users',
	'header' => '<th>' . $nframework->language['username'] . '</th><th>' . $nframework->language['options'] . '</th>',
	'pipeline' => [
		[
			'$match' => [
				'username' => ['$ne' => 'guest']
			]
		]
	],
	'columns' => [
		'username',
		'_id',
	],
	'columnDefs' => [
		'1' => ['render' => "'<a href=\"user.php?_id='+data+'\" class=\"button primary btn btn-primary\"><span class=\"mif-pencil\"></span></a>'
		+'<a href=\"javascript:dialogpass_open(\''+data+'\')\" class=\"button alert btn btn-danger \"><span class=\"mif-key\"></span</a>'
		+'<a href=\"javascript:eliminar(\''+data+'\')\" class=\"button alert btn btn-danger \"><span class=\"mif-cross\"></span</a>'"],
	]
]);
$password = new inputText(['id' => 'password', 'name' => "password", 'caption' => $nframework->language['password'] . ':', 'required' => true]);

if ($nframework->isAjax()) {
	$id = is_string($_POST['eliminar'] ?? null) ? $_POST['eliminar'] : '';
	if (!csrfValidate()) {
		$result = ['error' => 'Formulario caducado, recargue la página.'];
	} elseif (!isValidObjectId($id)) {
		$result = ['error' => 'Usuario inválido.'];
	} elseif ($id === (string) $user->_id) {
		$result = ['error' => 'No puede eliminar su propio usuario.'];
	} else {
		$db = $m->{$config['sitedb']};
		$doc = $db->users->findOne(['_id' => toMongoId($id)], ['projection' => ['sessions' => 1]]);
		if ($doc === null) {
			$result = ['error' => 'El usuario ya no existe.'];
		} else {
			// Sus sesiones dejan de valer y se quita de los grupos.
			$sessions = array_values(array_filter((array) ($doc['sessions'] ?? []), 'is_string'));
			if ($sessions !== []) {
				$db->sessions->deleteMany(['_id' => ['$in' => $sessions]]);
			}
			$db->usersgroups->updateMany(['users' => toMongoId($id)], ['$pull' => ['users' => toMongoId($id)]]);
			$db->users->deleteOne(['_id' => toMongoId($id)]);
			$result = ['error' => false];
		}
	}
	return;
} else {
	$nframework->usecommon = true;
?>
	<div class="container ">
		<div class="box shadow-large-extra p-5">
			<div class="box-title"><?= $nframework->language['users'] ?></div>
			<a href="user.php" class="button primary "><span class="mif-add-person"></span>&nbsp;<?= $nframework->language['new'] ?></a>
			<?= $datatable; ?>
		</div>
	</div>
	<script>
		function eliminar(id) {
			Swal.fire({
				title: '¿Eliminar este usuario?',
				text: 'Se cerrarán sus sesiones y se quitará de sus grupos. No se puede deshacer.',
				icon: 'warning',
				showCancelButton: true,
				confirmButtonColor: '#d33',
				confirmButtonText: 'Sí, eliminar',
				cancelButtonText: 'Cancelar'
			}).then(function (r) {
				if (!r.isConfirmed) {
					return;
				}
				$.post(location.pathname, {eliminar: id, CSRFToken: <?= json_encode(csrfToken(NF_AJAX_FORM_ACTION)) ?>}).done(function (data) {
					if (data && data.error) {
						Swal.fire('Error', data.error, 'error');
						return;
					}
					toast('Usuario eliminado.');
					datatables['users'].clearPipeline();
					datatables['users'].draw(false);
				}).fail(function (x) {
					Swal.fire('Error', 'El servidor respondió ' + x.status + '.', 'error');
				});
			});
		}
	</script>



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
	</div>
	<style>
		#dialogPass {
			max-width: 600px;
			width: 90%;
			max-height: 80vh;
			padding: 2rem;
		}

		#dialogPass[open] {
			inset: 0;
			margin: auto;
		}

		#dialogPass::backdrop {
			background: rgba(0, 0, 0, 0.45);
			backdrop-filter: blur(3px);
		}
	</style>
	<script>
		const dialogpass = document.querySelector("#dialogPass");
		let usertochange = null;

		function dialogpass_open(id) {
			usertochange = id;
			dialogpass.showModal();
		}

		function dialogpass_close() {
			dialogpass.close();
		}

		function cambiar() {

			$.ajax({
				url: 'user.php?_id=' + usertochange,
				method: 'post',
				data: {
					op: 'password',
					password: $('#password').val(),
					CSRFToken: <?= json_encode(csrfToken(NF_AJAX_FORM_ACTION)) ?>
				},
				success: function(respuesta) {
					nAjaxFormDone(respuesta);
					dialogpass_close();
				},
				error: function() {
					console.log("No se ha podido obtener la información");
					dialogpass_close();
				}
			});
		}
	</script>

<?php } ?>