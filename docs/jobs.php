<?php
require 'common.php';
$panel = new JobDashboard();
?>
<div class="container">
	<?= docHeader('Colas de trabajo', 'Para tareas que no deben hacer esperar al usuario (enviar correos, generar reportes, llamar a servicios externos): la página guarda el trabajo en la colección <code>jobs</code> y un proceso <code>job_worker.php</code> lo ejecuta, con reintentos si falla.') ?>

	<h3>1. Definir el trabajo</h3>
	<p>Cree la clase en un archivo <code>class.NombreJob.php</code> dentro del <code>include_path</code> (p.ej. <code>includes/</code>) para que el trabajador la encuentre. El constructor debe recibir y guardar <code>$payload</code>.</p>
	<?= docCode(<<<'PHP'
<?php
// includes/class.EnviarCorreoJob.php
class EnviarCorreoJob extends Job
{
    public $queue = 'email';      // cola (por defecto 'default')
    public $maxAttempts = 5;      // intentos antes de marcarlo como fallido
    public $delay = 0;            // segundos antes de la primera ejecución

    public function __construct(public $payload = [])
    {
    }

    public function handle()
    {
        $mail = nfMailer();       // PHPMailer con la configuración SMTP del sitio
        $mail->addAddress($this->payload['para']);
        $mail->Subject = $this->payload['asunto'];
        $mail->Body = $this->payload['html'];
        $mail->send();            // si lanza excepción, el trabajo se reintenta en 5 s
    }
}
PHP) ?>
	<p class="remark"><code>nfMailer()</code> se define en <code>nframework/router.php</code>; si el trabajador no carga el router, configure PHPMailer en la clase.</p>

	<h3>2. Encolar desde una página</h3>
	<?= docCode(<<<'PHP'
EnviarCorreoJob::dispatch([
    'para' => $cliente['email'],
    'asunto' => 'Su pedido ' . $pedido['folio'],
    'html' => $html,
]);
$result = ['error' => false, 'js' => 'toast("Le enviaremos un correo de confirmación")'];
PHP) ?>

	<h3>3. Ejecutar el trabajador</h3>
	<?= docCode(<<<'BASH'
# Desde includes/: procesa la cola "email"; cada trabajo tiene 5 s antes de considerarse abandonado.
php job_worker.php email 5

# Como servicio (systemd), uno por cola:
# ExecStart=/usr/bin/php /var/www/sitio/includes/job_worker.php email 5
BASH, 'bash') ?>
	<p>El trabajador toma el siguiente trabajo pendiente cuya hora ya pasó, ejecuta <code>handle()</code> y lo borra si termina bien. Si lanza una excepción llama a <code>retry(5)</code>; al agotar <code>maxAttempts</code> lo marca con <code>failed_at</code> y <code>error</code>. Solo instancia clases que heredan de <code>Job</code>.</p>

	<h3>Estado de las colas</h3>
	<p><code>JobDashboard</code> muestra pendientes y fallidos por cola:</p>
	<?= $m->{$config['sitedb']}->jobs->countDocuments() > 0 ? $panel->render() : '<p class="text-muted">No hay trabajos en la colección <code>jobs</code>.</p>' ?>
	<?= docCode('echo (new JobDashboard())->render();') ?>

	<h3>Documento en la colección <code>jobs</code></h3>
	<?= docCode(<<<'JS'
{
  queue: "email",
  class: "EnviarCorreoJob",
  payload: { para: "...", asunto: "...", html: "..." },
  attempts: 0,
  max_attempts: 5,
  delay_until: ISODate("..."),   // no se ejecuta antes de esta hora
  created_at: ISODate("..."),
  failed_at: null,               // fecha si agotó los intentos
  error: null
}
JS, 'javascript') ?>
</div>
