<?php
require 'common.php';

// Demostración: la hora del servidor y la carga, empujadas cada segundo.
if (($_GET['stream'] ?? '') === 'demo') {
	nfSSE::stream(fn (nfSSE $sse) => [
		'hora' => date('H:i:s'),
		'carga' => function_exists('sys_getloadavg') ? round(sys_getloadavg()[0], 2) : null,
		'tick' => $sse->ticks,
	], ['interval' => 1, 'maxtime' => 20]);
	exit;
}
?>
<div class="container">
	<?= docHeader('Tiempo real (SSE)', '<code>nfSSE</code> envía datos del servidor al navegador por una conexión abierta (<a href="https://developer.mozilla.org/es/docs/Web/API/Server-sent_events" target="_blank" rel="noopener">Server-Sent Events</a>) en lugar de consultar cada N segundos. No necesita WebSockets ni servicios externos: es una respuesta HTTP que no termina enseguida.') ?>

	<h3>En vivo</h3>
	<div class="card p-4">
		<p class="m-0">Hora del servidor: <b id="sseHora">…</b> · carga: <b id="sseCarga">…</b> · evento <b id="sseTick">…</b> · conexión: <span id="sseEstado" class="tag">conectando</span></p>
		<p class="text-small fg-gray mb-0">La conexión dura 20 s; el navegador reconecta solo y el contador vuelve a empezar.</p>
	</div>
	<script>
	$(function () {
		if (!window.EventSource) { $('#sseEstado').text('el navegador no admite EventSource'); return; }
		var es = new EventSource('?stream=demo');
		es.onopen = function () { $('#sseEstado').text('abierta').addClass('success').removeClass('alert'); };
		es.onerror = function () { $('#sseEstado').text('reconectando').addClass('alert').removeClass('success'); };
		es.addEventListener('update', function (e) {
			var d = JSON.parse(e.data);
			$('#sseHora').text(d.hora); $('#sseCarga').text(d.carga); $('#sseTick').text(d.tick);
		});
	});
	</script>

	<h3>Servidor</h3>
	<?= docCode(<<<'PHP'
<?php
require 'include.php';
requireGroup('admins');                      // los permisos se revisan en cada reconexión

if (($_GET['stream'] ?? '') === '1') {
    nfSSE::stream(function (nfSSE $sse) use ($m, $config) {
        $pendientes = $m->{$config['sitedb']}->pedidos->countDocuments(['estado' => 'pendiente']);
        if ($pendientes === 0) {
            return false;                    // termina: evento 'end'
        }
        return ['pendientes' => $pendientes]; // evento 'update' solo si cambió
    }, ['interval' => 2, 'maxtime' => 30]);
    exit;                                    // nada más después del flujo
}
$nframework->usecommon = true;
PHP) ?>
	<table class="table compact">
		<thead><tr><th>Opción</th><th>Por defecto</th><th></th></tr></thead>
		<tbody>
			<tr><td><code>interval</code></td><td>1</td><td>Segundos entre llamadas a la función (mínimo 0.2).</td></tr>
			<tr><td><code>maxtime</code></td><td>30</td><td>Segundos que dura la conexión; después el navegador reconecta.</td></tr>
			<tr><td><code>retry</code></td><td>2000</td><td>Milisegundos que espera el navegador para reconectar.</td></tr>
			<tr><td><code>onlychanges</code></td><td>true</td><td>Enviar <code>update</code> solo si los datos cambiaron.</td></tr>
			<tr><td><code>event</code></td><td><code>update</code></td><td>Nombre del evento de los datos.</td></tr>
			<tr><td><code>heartbeat</code></td><td>15</td><td>Segundos sin enviar nada tras los que se manda un comentario, para detectar conexiones cerradas.</td></tr>
		</tbody>
	</table>
	<p>La función recibe el objeto <code>nfSSE</code>: <code>$sse->ticks</code>, <code>$sse->lastEventId</code> (cabecera <code>Last-Event-ID</code> al reconectar) y <code>$sse->send($datos, 'evento')</code> para eventos propios.</p>

	<h3>Navegador</h3>
	<?= docCode(<<<'JS'
var es = new EventSource('?stream=1');
es.addEventListener('update', function (e) {
    var d = JSON.parse(e.data);
    $('#pendientes').text(d.pendientes);
});
es.addEventListener('end', function () { es.close(); });   // si no, reconecta
JS, 'javascript') ?>

	<h3>Ya integrado</h3>
	<ul>
		<li><a href="backgroundps.php"><code>bgprocess::renderDashboard()</code></a>: estado y final del log de cada proceso por <code>/nframework/kernel.php?op=stream</code>.</li>
		<li><code>JobDashboard::render('?stream=jobs')</code> y <a href="/admin/workers/">Admin → Workers</a>: contadores de las colas y aviso cuando cambian los workers (ver <a href="jobs.php">Colas de trabajo</a>).</li>
	</ul>

	<h3>Límites</h3>
	<ul>
		<li>Cada conexión abierta ocupa un proceso de PHP-FPM durante <code>maxtime</code>. Con muchos usuarios mirando a la vez suba <code>pm.max_children</code> o use un <code>interval</code> y <code>maxtime</code> menores. HTTP/1.1 permite unas 6 conexiones por dominio y pestaña: use un flujo por página.</li>
		<li>Antes de transmitir se cierra la sesión (<code>session_write_close()</code>) para no bloquear las demás peticiones del usuario: lo que cambie en <code>$_SESSION</code> durante el flujo no se guarda.</li>
		<li>Se envía <code>X-Accel-Buffering: no</code> para que nginx no acumule la respuesta. Si hay otro proxy o CDN delante, desactive ahí también el buffering para <code>text/event-stream</code>.</li>
		<li>Es de un solo sentido (servidor → navegador). Para enviar datos al servidor siga usando AJAX.</li>
	</ul>
</div>
