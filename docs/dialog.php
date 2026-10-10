<?php
$noobfuscate = true;
require 'common.php';

// Controles del diálogo: se dibujan dentro de 'content'.
$nombre = new inputText(['name' => 'contacto_nombre', 'caption' => 'Nombre']);
$correo = new inputText(['name' => 'contacto_correo', 'caption' => 'Correo', 'type' => 'email']);

$dialogo = new Dialog([
	'id' => 'dlgContacto',
	'title' => 'Nuevo contacto',
	'content' => '<div class="row"><div class="cell">' . $nombre . $correo . '</div></div>',
]);

if ($nframework->isAjax()) {
	// El botón Aceptar envía el formulario del diálogo (#dlgContacto_form) a esta página.
	$correoEnviado = (string) ($_POST['contacto_correo'] ?? '');
	$result = filter_var($correoEnviado, FILTER_VALIDATE_EMAIL)
		? ['error' => false, 'ids' => ['contactoGuardado' => htmlspecialchars((string) ($_POST['contacto_nombre'] ?? '')) . ' &lt;' . htmlspecialchars($correoEnviado) . '&gt;']]
		: ['error' => 'Correo inválido'];
	return;
}

$javas->addjs(<<<'JS'
$("#abrirContacto").click(function () {
	dlgContacto.showModal();          // la constante JS se llama igual que el id del diálogo
});
$("#dlgContacto_btnAcept").click(function () {
	$.post(location.pathname, $("#dlgContacto_form").serialize(), function (respuesta) {
		nAjaxFormDone(respuesta);     // mismo manejo que secureform(): error, ids, js
		if (!respuesta.error) dlgContacto.close();
	}, 'json');
});

$("#confirmar").click(function () {
	Swal.fire({title: '¿Continuar?', text: 'Confirmación con SweetAlert2', icon: 'question', showCancelButton: true})
		.then(function (r) { if (r.isConfirmed) toast('Confirmado'); });
});
$("#metroDialog").click(function () {
	Metro.dialog.create({
		title: 'Diálogo de Metro UI',
		content: '<div>Creado desde JavaScript, sin HTML previo.</div>',
		closeButton: true
	});
});
JS, 'ready');
?>
<div class="container">
	<?= docHeader('Diálogos', '<code>Dialog</code> crea un <code>&lt;dialog&gt;</code> HTML con título, un formulario y los botones Cerrar y Aceptar. Para confirmaciones rápidas basta con SweetAlert2 o <code>Metro.dialog</code>, que ya están cargados.') ?>

	<?= $dialogo ?>
	<button class="button primary" id="abrirContacto"><span class="mif-user-plus"></span> Nuevo contacto</button>
	<span class="ml-2">Guardado: <b id="contactoGuardado">—</b></span>
	<?= docCode(<<<'PHP'
$nombre = new inputText(['name' => 'contacto_nombre', 'caption' => 'Nombre']);
$correo = new inputText(['name' => 'contacto_correo', 'caption' => 'Correo', 'type' => 'email']);

$dialogo = new Dialog([
    'id' => 'dlgContacto',
    'title' => 'Nuevo contacto',
    'content' => '<div class="row"><div class="cell">' . $nombre . $correo . '</div></div>',
]);
echo $dialogo;

// Abrir y aceptar: los elementos se llaman #{id}, #{id}_form, #{id}_btnAcept, #{id}_btnClose.
$javas->addjs('
$("#abrirContacto").click(function () { dlgContacto.showModal(); });
$("#dlgContacto_btnAcept").click(function () {
    $.post(location.pathname, $("#dlgContacto_form").serialize(), function (r) {
        nAjaxFormDone(r);
        if (!r.error) dlgContacto.close();
    }, "json");
});', 'ready');
PHP) ?>
	<p class="remark">El formulario del diálogo incluye los campos ocultos <code>op</code> (valor <code>agregar</code>) y <code>pos</code>, que usa <a href="arraylist.php">embededArray</a>. Las opciones <code>onShow</code>, <code>onAcept</code> y <code>onClose</code> todavía no se aplican: conecte los eventos con JavaScript como arriba.</p>

	<h3>Confirmaciones rápidas</h3>
	<button class="button" id="confirmar">SweetAlert2</button>
	<button class="button" id="metroDialog">Metro.dialog</button>
	<?= docCode(<<<'JS'
Swal.fire({title: '¿Continuar?', icon: 'question', showCancelButton: true})
    .then(function (r) { if (r.isConfirmed) toast('Confirmado'); });

Metro.dialog.create({title: 'Diálogo de Metro UI', content: '<div>...</div>', closeButton: true});
JS, 'javascript') ?>
</div>
