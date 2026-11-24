<?php
require '../common2.php';

// Configuración de ClamAV (clamd por TCP) en configs/site → $config['clamav']; ver includes/class.ClamAV.php.
$dataset = new dataset(
	[
		'collection' => $m->{$config['sitedb']}->configs,
		'_id' => 'site',
		'simpleid' => true,
		'nameprefix' => 'data'
	]
);

/**
 * Opciones de ClamAV enviadas por el formulario (data[clamav.*]) como texto.
 *
 * @param array<string, mixed> $data
 * @return array{enabled: bool, host: string, port: string, timeout: string, maxsize: string, onerror: string}
 */
function clamavFromPost(array $data): array
{
	$options = [];
	foreach (['host', 'port', 'timeout', 'maxsize'] as $key) {
		$value = $data['clamav.' . $key] ?? '';
		$options[$key] = is_string($value) ? trim($value) : '';
	}
	$options['enabled'] = !empty($data['clamav.enabled']) && is_scalar($data['clamav.enabled']);
	$options['onerror'] = is_string($data['clamav.onerror'] ?? null) ? $data['clamav.onerror'] : 'reject';
	return $options;
}

/**
 * Prueba la conexión: PING, VERSION, un archivo limpio y la firma de prueba EICAR.
 *
 * @param array<string, mixed> $options host, port, timeout y maxsize
 * @return array{ok: bool, message: string, log: string}
 */
function clamavTest(array $options): array
{
	$av = new ClamAV($options);
	$log = [];
	$step = static function (string $label, callable $fn) use (&$log) {
		$start = microtime(true);
		try {
			$value = $fn();
		} catch (RuntimeException $e) {
			$value = $e;
		}
		$ms = round((microtime(true) - $start) * 1000);
		$text = $value instanceof Throwable ? 'ERROR: ' . $value->getMessage() : (is_array($value) ? $value['message'] : (string) $value);
		$log[] = sprintf('%-16s %6d ms  %s', $label, $ms, $text);
		return $value;
	};

	$server = $av->options['host'] . ':' . $av->options['port'];
	$log[] = 'Servidor: tcp://' . $server . ' · espera ' . $av->options['timeout'] . ' s · máximo ' . $av->options['maxsize'] . ' MB';
	$pong = $step('PING', fn() => $av->ping() ? 'PONG' : throw new RuntimeException('sin respuesta PONG'));
	if ($pong instanceof Throwable) {
		return ['ok' => false, 'message' => 'No hay comunicación con clamd en ' . $server . '.', 'log' => implode("\n", $log)];
	}
	$version = $step('VERSION', fn() => $av->version());
	$clean = $step('Archivo limpio', fn() => $av->scanString('Prueba de nframework ' . date('c')));
	$eicar = $step('Firma EICAR', fn() => $av->scanString(ClamAV::eicar()));

	$ok = is_array($clean) && $clean['status'] === 'OK' && is_array($eicar) && $eicar['status'] === 'FOUND';
	if ($ok) {
		$message = 'clamd responde y detecta la firma de prueba EICAR.' . (is_string($version) ? "\n" . $version : '');
	} elseif (is_array($eicar) && $eicar['status'] === 'OK') {
		$message = 'clamd responde pero NO detectó la firma EICAR: revise que las firmas estén cargadas (freshclam).';
	} else {
		$message = 'clamd responde pero el análisis falló: ' . (is_array($eicar) ? $eicar['message'] : (is_array($clean) ? $clean['message'] : ''));
	}
	return ['ok' => $ok, 'message' => $message, 'log' => implode("\n", $log)];
}

$saved = ClamAV::configOptions($config['clamav'] ?? []);

$enabled = new inputCheckbox(['dataset' => &$dataset, 'field' => 'clamav.enabled', 'caption' => 'Habilitar ClamAV: analizar los archivos que se suban al sitio']);
$host = new inputText(['dataset' => &$dataset, 'field' => 'clamav.host', 'caption' => 'Servidor clamd:', 'placeholder' => '127.0.0.1', 'validate' => 'maxlength=253', 'tags' => ['maxlength' => 253]]);
$port = new inputNumber(['dataset' => &$dataset, 'field' => 'clamav.port', 'caption' => 'Puerto TCP:', 'placeholder' => (string) ClamAV::DEFAULT_PORT, 'validate' => 'integer max=65535', 'data_validate' => 'integer', 'min' => 1, 'max' => 65535, 'step' => 1]);
$timeout = new inputNumber(['dataset' => &$dataset, 'field' => 'clamav.timeout', 'caption' => 'Tiempo de espera (segundos):', 'placeholder' => '10', 'validate' => 'integer max=120', 'data_validate' => 'integer', 'min' => 1, 'max' => 120, 'step' => 1]);
$maxsize = new inputNumber(['dataset' => &$dataset, 'field' => 'clamav.maxsize', 'caption' => 'Tamaño máximo por archivo (MB):', 'placeholder' => '25', 'validate' => 'integer max=4095', 'data_validate' => 'integer', 'min' => 1, 'max' => 4095, 'step' => 1]);
$onerror = new select(['dataset' => &$dataset, 'field' => 'clamav.onerror', 'caption' => 'Si clamd no responde o no puede analizar:', 'options' => [
	'reject' => 'Rechazar la subida (más seguro)',
	'accept' => 'Aceptar el archivo y registrarlo en el log',
]]);

if ($nframework->isAjax()) {
	$op = is_string($_POST['op'] ?? null) ? $_POST['op'] : '';
	$data = is_array($_POST['data'] ?? null) ? $_POST['data'] : [];
	$options = clamavFromPost($data);
	if (!csrfValidate()) {
		$result = ['error' => 'Formulario caducado, recargue la página.', 'ok' => false, 'message' => 'Formulario caducado, recargue la página.'];
	} elseif ($op === 'save') {
		$error = ClamAV::validationError($options, $options['enabled']);
		if ($error === '' && !in_array($options['onerror'], ['reject', 'accept'], true)) {
			$error = 'Opción desconocida para cuando clamd falla.';
		}
		if ($error === '') {
			try {
				$error = $dataset->save();
			} catch (Exception $e) {
				$error = $e->getMessage();
			}
		}
		$result = ['error' => $error === '' ? false : $error];
	} elseif ($op === 'test') {
		// Prueba los valores escritos, aunque no estén guardados ni ClamAV esté habilitado.
		if (($error = ClamAV::validationError($options)) !== '') {
			$result = ['ok' => false, 'message' => strip_tags(str_replace('<br>', "\n", $error))];
		} elseif (!nflogAttempt('clamavtest:' . $user->_id, 20, 900)) {
			$result = ['ok' => false, 'message' => 'Demasiadas pruebas seguidas; espere 15 minutos.'];
		} else {
			$result = clamavTest($options);
		}
	}
	return;
}

$nframework->usecommon = true;
$nframework->metas['title'] = 'Antivirus ClamAV';
$javas->addjs(<<<'JS'
$('#clamavTestBtn').on('click', function () {
	var btn = $(this), log = $('#clamavTestLog');
	var data = $('#clamavform').serializeArray().filter(function (f) { return f.name !== 'op'; });
	data.push({name: 'op', value: 'test'});
	btn.prop('disabled', true).find('.caption').text('Probando…');
	$.post(location.pathname, $.param(data)).done(function (r) {
		Swal.fire({icon: r.ok ? 'success' : 'error', title: r.ok ? 'ClamAV funciona' : 'Prueba fallida', text: r.message || ''});
		log.text(r.log || '').toggleClass('d-none', !r.log);
	}).fail(function (x) {
		Swal.fire({icon: 'error', title: 'Error', text: 'El servidor respondió ' + x.status + '.'});
	}).always(function () {
		btn.prop('disabled', false).find('.caption').text('Probar conexión');
	});
});
JS, 'ready');

$help = static fn(string $html): string => '<small class="d-block fg-gray mt-1 mb-2">' . $html . '</small>';
$status = empty($saved['enabled'])
	? '<span class="tag">Deshabilitado</span>'
	: '<span class="tag success">Habilitado</span> <code>' . htmlspecialchars(($saved['host'] ?? '') . ':' . ($saved['port'] ?? ClamAV::DEFAULT_PORT)) . '</code>';
?>
<div class="container">
	<div class="bo">
		<h1 class="text-weight-10 text-center gradient gr-text-blue">Antivirus ClamAV</h1>
		<p class="text-center fg-gray">Conexión con el servicio <code>clamd</code> por TCP para analizar los archivos que reciba el sitio. Estado guardado: <?= $status ?></p>
	</div>
	<div class="box shadow-large-extra p-5">
		<?= secureform('', false, 'clamavform') ?>
		<div class="grid">
			<div class="row">
				<div class="cell"><?= $enabled ?><?= $help('Se analizan las subidas de <code>/nframework</code>: <code>inputFile</code>/<code>inputFiles</code> y archivos de nftables (<code>uploadfile.php</code>), imágenes de <code>inputMCE</code> (<code>tinymceupload.php</code>) e importaciones (<code>nfimport.php</code>). Un archivo infectado se borra y se rechaza. El código propio del sitio puede usar <code>ClamAV::uploadError()</code> o <code>ClamAV::fromConfig()</code>.') ?></div>
			</div>
			<div class="row">
				<div class="cell-md-8"><?= $host ?><?= $help('Nombre o IP del equipo donde corre clamd (<code>TCPAddr</code> en <code>clamd.conf</code>), sin <code>tcp://</code> ni puerto.') ?></div>
				<div class="cell-md-4"><?= $port ?><?= $help('<code>TCPSocket</code> en <code>clamd.conf</code>; por omisión ' . ClamAV::DEFAULT_PORT . '.') ?></div>
			</div>
			<div class="row">
				<div class="cell-md-6"><?= $timeout ?><?= $help('Para conectar y para esperar el resultado. Archivos grandes pueden tardar varios segundos. Por omisión 10.') ?></div>
				<div class="cell-md-6"><?= $maxsize ?><?= $help('Los archivos mayores no se envían y se reportan como error. No debe superar <code>StreamMaxLength</code> de <code>clamd.conf</code> (25 MB por omisión).') ?></div>
			</div>
			<div class="row">
				<div class="cell">
					<div class="remark warning">
						El protocolo de clamd no tiene autenticación ni cifrado: haga que escuche solo en <code>127.0.0.1</code> o en una red
						privada y bloquee el puerto en el firewall.
					</div>
				</div>
			</div>
			<div class="row">
				<div class="cell-md-6"><?= $onerror ?><?= $help('Con «Rechazar», si clamd se cae nadie puede subir archivos hasta que vuelva.') ?></div>
			</div>
			<div class="row">
				<div class="cell">
					<div id="clamavTestBox" class="border bd-default p-4 mt-2">
						<h5 class="mt-0"><span class="mif-security"></span>&nbsp;Probar la configuración</h5>
						<p class="fg-gray mt-0">
							Con los valores escritos arriba, <b>aunque todavía no estén guardados ni ClamAV esté habilitado</b>: envía <code>PING</code> y <code>VERSION</code>,
							analiza un texto limpio y la firma de prueba <a href="https://www.eicar.org/download-anti-malware-testfile/" target="_blank" rel="noopener">EICAR</a>
							(inofensiva), que clamd debe reportar como virus.
						</p>
						<button type="button" id="clamavTestBtn" class="button info"><span class="mif-security"></span>&nbsp;<span class="caption">Probar conexión</span></button>
						<pre id="clamavTestLog" class="d-none mt-2 p-2 border bd-default" style="max-height:300px;overflow:auto;white-space:pre-wrap;font-size:.8rem"></pre>
					</div>
				</div>
			</div>
			<div class="row mt-4">
				<div class="cell">
					<button class="button primary secureop" value="save"><span class="mif-floppy-disk"></span>&nbsp;Guardar</button>
				</div>
			</div>
		</div>
		</form>
	</div>
</div>
