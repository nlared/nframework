<?php
require 'common.php';

// Aviso al terminar de cargar la página, generado desde PHP.
notify('', 'Esta página se cargó a las ' . date('H:i:s'));

// Biblioteca extra solo en esta página, con atributos (SRI) mediante UIManager.
$nframework->ui->addJs('120', 'https://cdnjs.cloudflare.com/ajax/libs/dayjs/1.11.13/dayjs.min.js', ['defer' => 'defer']);
$javas->addjs('window.addEventListener("load", function () { $("#hoyJs").text(dayjs().format("YYYY-MM-DD HH:mm")); });', 'ready');
?>
<div class="container">
	<?= docHeader('Notificaciones y utilidades', 'Avisos, voz, tema claro/oscuro y cómo agregar CSS o JavaScript a una página.') ?>

	<h3>Avisos y voz</h3>
	<button class="button" onclick="toast('Hola desde JavaScript')">toast()</button>
	<button class="button" onclick="speak('Hola, esto es nframework')"><span class="mif-volume-high"></span> speak()</button>
	<?= docCode(<<<'PHP'
// Desde PHP: se ejecutan cuando la página termina de cargar.
notify('', 'Datos guardados');
speak('Su turno es el 25');

// En una respuesta AJAX, use la clave js de $result:
$result = ['error' => false, 'js' => 'toast(' . json_encode('Registro ' . $folio . ' creado') . ');'];
PHP) ?>

	<h3>Tema claro / oscuro</h3>
	<p><code>ThemeSwitcher</code> permite elegir entre claro, oscuro o el del sistema; la elección se guarda en el navegador.</p>
	<?= $themeswitcher ?>
	<?= docCode(<<<'PHP'
echo $themeswitcher;            // instancia global creada por include.php
// o: echo new ThemeSwitcher();
PHP) ?>

	<h3>CSS y JavaScript por página</h3>
	<p><code>$nframework->csss</code> y <code>$nframework->jss</code> son arreglos ordenados por clave: las claves bajas cargan primero (jQuery es <code>'001'</code>, Metro UI <code>'050'</code>, nframework <code>'100'</code>). Use una clave nueva para agregar y una existente para reemplazar.</p>
	<p>dayjs cargado solo en esta página: <b id="hoyJs">…</b></p>
	<?= docCode(<<<'PHP'
$nframework->csss['110'] = 'https://cdn.example.com/calendario.css';
$nframework->jss['110'] = 'https://cdn.example.com/calendario.js';

// Con atributos (defer, integrity, crossorigin) use UIManager; tiene prioridad sobre jss con la misma clave.
$nframework->ui->addJs('120', 'https://cdnjs.cloudflare.com/ajax/libs/dayjs/1.11.13/dayjs.min.js', ['defer' => 'defer']);

// Bibliotecas que el framework sabe agregar:
$nframework->addjqueryui();     // jQuery UI
$nframework->addfileupload();   // blueimp file upload
PHP) ?>

	<h3>Usuario en la barra superior</h3>
	<p><code>$user->usermenu()</code> dibuja el menú de usuario (iniciar sesión o perfil y salir) que aparece en la barra de esta documentación. <code>$user->gravatar()</code> devuelve la URL de su foto de perfil (WebP de 32×32 en <code>/images/pngtowebp/users/</code>).</p>

	<h3>Iconos</h3>
	<p><?= new Icon('home') ?> <?= new Icon('user') ?> <?= new Icon('cog') ?> <code>new Icon('home')</code> imprime <code>&lt;span class="icon mif-home"&gt;</code>; con una ruta de imagen imprime un <code>&lt;img&gt;</code>. Catálogo completo en <a href="https://metroui.org.ua/icons.html" target="_blank">Metro UI icons</a>.</p>
</div>
