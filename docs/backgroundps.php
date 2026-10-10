<?php
require 'common.php';

// Comando que tarda ~20 s: imprime su avance en el archivo de log.
$bg = new bgprocess([
	'id' => 'demo_process',
	'cmd' => 'php ' . escapeshellarg(__DIR__ . '/tmp/long_process.php'),
	'logfile' => __DIR__ . '/tmp/demo_process.log',
]);
?>
<div class="container">
	<?= docHeader('Procesos en segundo plano', '<code>bgprocess</code> lanza un comando del sistema que sigue corriendo aunque el usuario cierre la página, y permite consultar su avance o detenerlo. El PID se guarda en la sesión del usuario.') ?>

	<h3>Panel en vivo</h3>
	<div class="p-4 border bd-default">
		<?= $bg->renderDashboard() ?>
	</div>
	<p class="text-small">El proceso de ejemplo (<code>tmp/long_process.php</code>) cuenta hasta 20, un paso por segundo.</p>

	<h3>Código</h3>
	<?= docCode(<<<'PHP'
$bg = new bgprocess([
    'id' => 'demo_process',                                    // identifica el proceso en la sesión
    'cmd' => 'php ' . escapeshellarg(__DIR__ . '/tmp/long_process.php'),
    'logfile' => __DIR__ . '/tmp/demo_process.log',            // salida del comando
]);
echo $bg->renderDashboard();     // botones iniciar/detener y log que se actualiza solo
PHP) ?>

	<h3>Desde PHP</h3>
	<?= docCode(<<<'PHP'
if (!$bg->isRunning()) {
    $bg->start();
}
$estado = $bg->status();   // ['data' => contenido del log, 'isRunning' => bool]
$bg->stop();
PHP) ?>
	<p class="remark warning">Nunca arme <code>cmd</code> con datos del usuario sin <code>escapeshellarg()</code>. Para tareas que deben reintentarse o repartirse entre varios trabajadores use <a href="jobs.php">colas de trabajo</a>.</p>
</div>
