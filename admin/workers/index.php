<?php
require '../common2.php';

/*
 * Admin → Workers: estado de la cola de trabajos (colección jobs) y de los procesos job_worker.php, con la
 * configuración para dejarlos como servicio ya armada para este sitio (host, PHP, rutas y usuario reales).
 * Ver includes/class.Job.php, includes/job_worker.php y docs/jobs.php.
 */

$jobs = $m->{$config['sitedb']}->jobs;
$wHost = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');          // bloque de config.php (include.php ya lo validó)
$wPhp = PHP_BINDIR . '/php' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
if (!is_executable($wPhp)) {
	$wPhp = PHP_BINDIR . '/php';
}
$wScript = realpath(dirname(__DIR__, 2) . '/includes/job_worker.php') ?: dirname(__DIR__, 2) . '/includes/job_worker.php';
$wDir = dirname($wScript);
$wUser = function_exists('posix_getpwuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? 'www-data') : 'www-data';
$wSlug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($wHost)), '-');
$wLease = 300;   // reserva: más que lo que tarda el trabajo más largo
$wTempLog = sys_get_temp_dir() . '/nframework-worker-' . $wSlug . '.log';

/** Nombre de cola válido (también es el nombre de la instancia de systemd). */
function workersValidQueue($queue): ?string
{
	return is_string($queue) && preg_match('/^[A-Za-z0-9_.-]{1,64}$/', $queue) ? $queue : null;
}

$allWorkers = JobDashboard::workers();
$siteWorkers = JobDashboard::workersForHost($allWorkers, $wHost);

// Flujo SSE para los contadores en vivo (JobDashboard::render('?stream=jobs')): colas y workers del sitio.
if (($_GET['stream'] ?? '') === 'jobs') {
	nfSSE::stream(fn () => [
		'queues' => JobDashboard::queues(),
		'workers' => array_column(JobDashboard::workersForHost(JobDashboard::workers(), $wHost), 'pid'),
	], ['interval' => 2, 'maxtime' => 30]);
	exit;
}

if ($nframework->isAjax()) {
	$op = is_string($_POST['op'] ?? null) ? $_POST['op'] : '';
	if (!csrfValidate()) {
		$result = ['error' => 'Formulario caducado, recargue la página.'];
	} elseif ($op === 'start') {
		$queue = workersValidQueue($_POST['queue'] ?? 'default');
		if ($queue === null) {
			$result = ['error' => 'Nombre de cola inválido.'];
		} elseif (!function_exists('exec')) {
			$result = ['error' => 'exec() está deshabilitada en PHP: configure el worker como servicio.'];
		} else {
			// Proceso desligado de la petición; hereda el usuario de PHP-FPM y termina si se reinicia FPM o el servidor.
			$cmd = 'cd ' . escapeshellarg($wDir) . ' && HTTP_HOST=' . escapeshellarg($wHost) . ' nohup '
				. escapeshellarg($wPhp) . ' ' . escapeshellarg($wScript) . ' ' . escapeshellarg($queue) . ' ' . $wLease
				. ' >> ' . escapeshellarg($wTempLog) . ' 2>&1 < /dev/null & echo $!';
			$pid = (int) trim((string) exec($cmd));
			usleep(700000);
			$alive = $pid > 0 && is_dir('/proc/' . $pid);
			$result = $alive
				? ['error' => false, 'js' => 'setTimeout(function(){ location.reload(); }, 600)']
				: ['error' => 'El worker terminó al iniciar. Últimas líneas de ' . htmlspecialchars($wTempLog) . ':<pre>' . htmlspecialchars(implode("\n", array_slice(@file($wTempLog) ?: [], -15))) . '</pre>'];
		}
	} elseif ($op === 'stop') {
		$pid = (int) ($_POST['pid'] ?? 0);
		// Solo procesos job_worker.php de este sitio (no cualquier PID que llegue del navegador).
		$target = array_values(array_filter($siteWorkers, fn ($w) => $w['pid'] === $pid));
		if (!$target || !function_exists('posix_kill')) {
			$result = ['error' => 'No es un worker de este sitio.'];
		} elseif (!@posix_kill($pid, SIGTERM)) {
			$result = ['error' => 'No se pudo detener el proceso ' . $pid . ' (es de otro usuario). Si es un servicio deténgalo con systemctl o supervisorctl; si no, reiniciaría solo.'];
		} else {
			$result = ['error' => false, 'js' => 'setTimeout(function(){ location.reload(); }, 600)'];
		}
	} elseif ($op === 'retry' || $op === 'delete') {
		$id = $_POST['id'] ?? '';
		$filter = ['failed_at' => ['$ne' => null]];
		if ($id !== 'all') {
			if (!isValidObjectId($id)) {
				$result = ['error' => 'Trabajo inválido.'];
				return;
			}
			$filter['_id'] = toMongoId($id);
		}
		if ($op === 'retry') {
			$n = $jobs->updateMany($filter, [
				'$set' => ['failed_at' => null, 'attempts' => 0, 'delay_until' => new MongoDB\BSON\UTCDateTime()],
			])->getModifiedCount();
		} else {
			$n = $jobs->deleteMany($filter)->getDeletedCount();
		}
		$result = ['error' => false, 'js' => 'toast(' . json_encode(($op === 'retry' ? 'Reencolados: ' : 'Eliminados: ') . $n) . '); setTimeout(function(){ location.reload(); }, 800)'];
	}
	return;
}

$nframework->metas['title'] = 'Workers';
$queues = JobDashboard::queues();
$queueNames = array_values(array_unique(array_merge(['default'], array_keys($queues), array_column($siteWorkers, 'queue'))));
$withoutWorker = array_values(array_filter($queueNames, fn ($q) => ($queues[$q]['pending'] ?? 0) > 0 && !in_array($q, array_column($siteWorkers, 'queue'), true)));
$failedJobs = $jobs->find(['failed_at' => ['$ne' => null]], ['sort' => ['failed_at' => -1], 'limit' => 50]);
$e = fn ($s) => htmlspecialchars((string) $s);

// ---- Configuración para este sitio ----
$systemdUnit = "nframework-worker-$wSlug@.service";
$systemd = "# /etc/systemd/system/$systemdUnit
[Unit]
Description=nframework worker $wHost (cola %i)
After=network.target mongod.service

[Service]
Type=simple
User=$wUser
Group=$wUser
WorkingDirectory=$wDir
Environment=HTTP_HOST=$wHost
ExecStart=$wPhp $wScript %i $wLease
Restart=always
RestartSec=5
# La salida va al journal: journalctl -u 'nframework-worker-$wSlug@*' -f

[Install]
WantedBy=multi-user.target";
$systemdCmds = "sudo nano /etc/systemd/system/$systemdUnit     # pegue el contenido de arriba\nsudo systemctl daemon-reload\n"
	. implode("\n", array_map(fn ($q) => "sudo systemctl enable --now nframework-worker-$wSlug@$q", $queueNames))
	. "\nsystemctl status 'nframework-worker-$wSlug@*'";

$supervisor = '';
foreach ($queueNames as $q) {
	$supervisor .= "[program:nframework-worker-$wSlug-$q]
command=$wPhp $wScript $q $wLease
directory=$wDir
environment=HTTP_HOST=\"$wHost\"
user=$wUser
numprocs=1
autostart=true
autorestart=true
startsecs=3
stopwaitsecs=3600
redirect_stderr=true
stdout_logfile=/var/log/supervisor/nframework-worker-$wSlug-$q.log

";
}
$supervisor = "# /etc/supervisor/conf.d/nframework-worker-$wSlug.conf\n" . rtrim($supervisor);
$supervisorCmds = "sudo apt install supervisor\nsudo nano /etc/supervisor/conf.d/nframework-worker-$wSlug.conf     # pegue el contenido de arriba\nsudo supervisorctl reread\nsudo supervisorctl update\nsudo supervisorctl status";

$cron = "# sudo crontab -u $wUser -e\n" . implode("\n", array_map(
	fn ($q) => "* * * * * cd $wDir && HTTP_HOST=$wHost flock -n /tmp/nframework-worker-$wSlug-$q.lock $wPhp $wScript $q $wLease --once >> /tmp/nframework-worker-$wSlug-$q.log 2>&1",
	$queueNames
));
$restart = "sudo systemctl restart 'nframework-worker-$wSlug@*'    # o: sudo supervisorctl restart 'nframework-worker-$wSlug-*:*'";

$code = fn (string $text) => '<pre class="bg-light p-2" style="white-space:pre;overflow-x:auto"><code>' . htmlspecialchars($text) . '</code></pre>';

// Avisa (sin recargar) si arrancó o se detuvo un worker mientras la página está abierta.
$javas->addjs('var workersPids = ' . json_encode(array_column($siteWorkers, 'pid')) . ";
$(document).on('nf:jobs-update', 'table', function (e, data) {
	var now = (data.workers || []).slice().sort().join(','), before = workersPids.slice().sort().join(',');
	$('#workers-changed').toggleClass('d-none', now === before);
});", 'ready');
$javas->addjs('window.workersCsrf = ' . json_encode(csrfToken(NF_AJAX_FORM_ACTION)) . ';', 'general');
$javas->addjs(<<<'JS'
window.workersOp = function (data, confirmText) {
	var send = function () {
		data.CSRFToken = window.workersCsrf;
		$.post(location.pathname, data, null, 'json')
			.done(nAjaxFormDone)
			.fail(function (x) { Swal.fire({icon: 'error', title: 'Error', text: 'El servidor respondió ' + x.status + '.'}); });
	};
	if (!confirmText) { send(); return; }
	Swal.fire({icon: 'question', text: confirmText, showCancelButton: true, confirmButtonText: 'Sí', cancelButtonText: 'Cancelar'})
		.then(function (r) { if (r.isConfirmed) { send(); } });
};
JS, 'general');
?>
<div class="container p-5">
	<div class="box shadow-large p-4 mb-4">
		<h3 class="mt-0"><span class="mif-cogs"></span> Workers de la cola de trabajos</h3>
		<p class="fg-gray">Los trabajos que encolan las páginas (<code>MiJob::dispatch()</code>: correos masivos, importaciones, reportes) se guardan en la colección <code>jobs</code> y los ejecuta un proceso <code>job_worker.php</code> que corre aparte, uno por cola. Sitio: <code><?= $e($wHost) ?></code>, base <code><?= $e($config['sitedb']) ?></code>.</p>

		<h4>Procesos</h4>
		<?php if ($siteWorkers): ?>
			<table class="table compact striped">
				<thead><tr><th>PID</th><th>Cola</th><th>Usuario</th><th>Memoria</th><th>Desde</th><th></th></tr></thead>
				<tbody>
				<?php foreach ($siteWorkers as $w): ?>
					<tr>
						<td><?= $w['pid'] ?></td>
						<td><code><?= $e($w['queue']) ?></code><?= $w['host'] === null ? ' <span class="fg-gray" title="No se pudo leer su HTTP_HOST (proceso de otro usuario)">(¿sitio?)</span>' : '' ?></td>
						<td><?= $e($w['uid'] !== null && function_exists('posix_getpwuid') ? (posix_getpwuid($w['uid'])['name'] ?? $w['uid']) : '') ?></td>
						<td><?= $w['rss'] !== null ? formatSize($w['rss']) : '' ?></td>
						<td><?= $w['started'] ? date('Y-m-d H:i', $w['started']) : '' ?></td>
						<td><button type="button" class="button mini alert" onclick="workersOp({op: 'stop', pid: <?= $w['pid'] ?>}, '¿Detener el worker <?= $w['pid'] ?>? Si es un servicio, se volverá a iniciar.')">Detener</button></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php else: ?>
			<div class="remark warning">
				<b>No hay ningún worker corriendo para este sitio.</b> Los trabajos se acumulan en <code>jobs</code> sin ejecutarse.
				Para dejarlo funcionando de forma permanente use una de las opciones de <a href="#instalar">Instalar como servicio</a> (recomendado: systemd).
				Para probar ahora mismo:
			</div>
		<?php endif; ?>
		<?php if (count($allWorkers) > count($siteWorkers)): ?>
			<p class="fg-gray text-small"><?= count($allWorkers) - count($siteWorkers) ?> worker(s) más de otros sitios en este servidor.</p>
		<?php endif; ?>
		<?php if ($withoutWorker): ?>
			<div class="remark alert">Colas con trabajos pendientes y sin worker: <?= implode(', ', array_map(fn ($q) => '<code>' . $e($q) . '</code>', $withoutWorker)) ?>.</div>
		<?php endif; ?>
		<div class="d-flex flex-wrap flex-align-center mt-2" style="gap:.5rem">
			<select id="workers-queue" data-role="select" data-filter="false" style="width:200px">
				<?php foreach ($queueNames as $q): ?><option value="<?= $e($q) ?>"><?= $e($q) ?></option><?php endforeach; ?>
			</select>
			<button type="button" class="button primary" onclick="workersOp({op: 'start', queue: $('#workers-queue').val()})"><span class="mif-play"></span> Iniciar worker temporal</button>
			<span class="fg-gray text-small">Corre como <code><?= $e($wUser) ?></code> hasta que se reinicie PHP-FPM o el servidor. Salida: <code><?= $e($wTempLog) ?></code></span>
		</div>
	</div>

	<div class="box shadow-large p-4 mb-4">
		<h4 class="mt-0">Colas</h4>
		<?php if ($queues): ?>
			<?= (new JobDashboard())->render('?stream=jobs') ?>
			<p id="workers-changed" class="remark info d-none">Cambiaron los workers del sitio. <a href="">Recargar</a></p>
			<p class="fg-gray text-small">Atrasados: pendientes cuya hora pasó hace más de <?= JobDashboard::OVERDUE / 60 ?> minutos (nadie los tomó).</p>
		<?php else: ?>
			<p class="fg-gray">La colección <code>jobs</code> está vacía.</p>
		<?php endif; ?>

		<?php $failedList = iterator_to_array($failedJobs); ?>
		<?php if ($failedList): ?>
			<h4>Trabajos fallidos <small class="fg-gray">(agotaron sus reintentos; últimos 50)</small></h4>
			<div class="mb-2">
				<button type="button" class="button" onclick="workersOp({op: 'retry', id: 'all'}, '¿Volver a encolar todos los fallidos?')"><span class="mif-loop2"></span> Reintentar todos</button>
				<button type="button" class="button alert" onclick="workersOp({op: 'delete', id: 'all'}, '¿Eliminar todos los fallidos?')"><span class="mif-bin"></span> Eliminar todos</button>
			</div>
			<table class="table compact striped">
				<thead><tr><th>Falló</th><th>Cola</th><th>Clase</th><th>Prioridad</th><th>Intentos</th><th>Error</th><th></th></tr></thead>
				<tbody>
				<?php foreach ($failedList as $j): ?>
					<tr>
						<td><?= $j['failed_at'] instanceof MongoDB\BSON\UTCDateTime ? $j['failed_at']->toDateTime()->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i') : '' ?></td>
						<td><code><?= $e($j['queue'] ?? '') ?></code></td>
						<td><code><?= $e(is_string($j['class'] ?? null) ? $j['class'] : '') ?></code></td>
						<td><?= (int) ($j['priority'] ?? 0) ?></td>
						<td><?= (int) ($j['attempts'] ?? 0) ?>/<?= (int) ($j['max_attempts'] ?? 0) ?></td>
						<td class="text-small"><?= $e(mb_strimwidth((string) ($j['error'] ?? 'sin mensaje'), 0, 300, '…')) ?></td>
						<td class="text-nowrap">
							<button type="button" class="button mini" onclick="workersOp({op: 'retry', id: '<?= $j['_id'] ?>'})">Reintentar</button>
							<button type="button" class="button mini alert" onclick="workersOp({op: 'delete', id: '<?= $j['_id'] ?>'}, '¿Eliminar este trabajo?')">Eliminar</button>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<div class="box shadow-large p-4" id="instalar">
		<h4 class="mt-0">Instalar como servicio</h4>
		<p class="fg-gray">Ya armado para este sitio: <code>HTTP_HOST=<?= $e($wHost) ?></code> (sin él el worker usaría el bloque <code>localhost</code> de config.php), <code><?= $e($wPhp) ?></code>, usuario <code><?= $e($wUser) ?></code> y un worker por cola (<?= implode(', ', array_map(fn ($q) => '<code>' . $e($q) . '</code>', $queueNames)) ?>). Reserva de <?= $wLease ?> s: un trabajo tomado no lo vuelve a tomar otro worker antes de ese tiempo.</p>
		<ul data-role="tabs" data-expand="true">
			<li><a href="#w-systemd">systemd (recomendado)</a></li>
			<li><a href="#w-supervisor">supervisor</a></li>
			<li><a href="#w-cron">cron</a></li>
		</ul>
		<div class="border bd-default no-border-top p-3">
			<div id="w-systemd">
				<p>Una plantilla para todas las colas; cada cola es una instancia (<code>@default</code>, <code>@email</code>…) que se reinicia sola si falla y arranca con el servidor.</p>
				<?= $code($systemd) ?>
				<?= $code($systemdCmds) ?>
			</div>
			<div id="w-supervisor">
				<?= $code($supervisor) ?>
				<?= $code($supervisorCmds) ?>
			</div>
			<div id="w-cron">
				<p>Si no puede instalar servicios: cada minuto procesa lo pendiente y termina (<code>--once</code>); <code>flock</code> evita que se encimen. Los trabajos esperan hasta un minuto.</p>
				<?= $code($cron) ?>
			</div>
		</div>
		<p class="mt-3"><b>Después de actualizar el código</b> (Admin → Actualizar, git pull) reinicie los workers: cargan las clases al iniciar y seguirían ejecutando la versión anterior.</p>
		<?= $code($restart) ?>
		<p class="fg-gray text-small">Agregue una cola nueva aquí cuando el sitio use otra (<code>public $queue = 'email';</code> en el Job); esta página lista las que ya tienen trabajos.</p>
	</div>
</div>
