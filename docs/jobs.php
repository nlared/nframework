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
    public $priority = 0;         // mayor = se toma antes dentro de la cola

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
	<p class="remark"><code>nfMailer()</code> está en <code>includes/functions.php</code>: disponible en páginas, rutas y en <code>job_worker.php</code>. Toma el servidor y remitente de <code>$config['smtp']</code> (Admin → Sitio).</p>

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
# Desde includes/. HTTP_HOST elige el sitio (bloque de config.php); sin él se usa localhost.
# Procesa la cola "email"; un trabajo tomado queda reservado 300 s (otro worker no lo toma antes).
HTTP_HOST=www.ejemplo.com php job_worker.php email 300

# Procesar lo pendiente y terminar (cron):
HTTP_HOST=www.ejemplo.com php job_worker.php email 300 --once
BASH, 'bash') ?>
	<p><a href="/admin/workers/">Admin → Workers</a> muestra los workers que corren, las colas y los trabajos fallidos (con su error, para reintentarlos o eliminarlos), inicia un worker temporal y da la configuración de systemd, supervisor o cron ya armada para el sitio. Reinicie los workers después de actualizar el código: cargan las clases al iniciar.</p>
	<p>El trabajador toma el siguiente trabajo pendiente cuya hora ya pasó, ejecuta <code>handle()</code> y lo borra si termina bien. Si lanza una excepción llama a <code>retry(5)</code>; al agotar <code>maxAttempts</code> lo marca con <code>failed_at</code> y <code>error</code>. Solo instancia clases que heredan de <code>Job</code>.</p>

	<h3>Prioridad</h3>
	<p>Entre los trabajos ya vencidos de una cola, el worker toma primero el de mayor <code>$priority</code> y, a igual prioridad, el que venció antes (orden de llegada). El valor por omisión es <code>0</code>; uno negativo deja el trabajo detrás de los normales. La prioridad no adelanta a un trabajo cuyo <code>$delay</code> no ha pasado, y se conserva al reintentarlo.</p>
	<?= docCode(<<<'PHP'
class ReporteJob extends Job
{
    public $queue = 'reports';

    public function __construct(public $payload = [])
    {
        // Por trabajo: el reporte que un usuario está esperando va antes que los nocturnos.
        $this->priority = !empty($payload['urgente']) ? 10 : -5;
    }

    public function handle() { /* ... */ }
}
PHP) ?>
	<p class="remark">Si llegan sin pausa trabajos de alta prioridad, los de baja esperan indefinidamente en esa cola. Para tareas que nunca deben bloquearse entre sí, use colas distintas, cada una con su worker.</p>

	<h3>Estado de las colas</h3>
	<p><code>JobDashboard</code> muestra pendientes y fallidos por cola:</p>
	<?= $m->{$config['sitedb']}->jobs->countDocuments() > 0 ? $panel->render() : '<p class="text-muted">No hay trabajos en la colección <code>jobs</code>.</p>' ?>
	<?= docCode(<<<'PHP'
echo (new JobDashboard())->render();               // tabla fija

// En vivo por Server-Sent Events (ver sse.php): la página atiende ?stream=jobs antes de imprimir nada.
if (($_GET['stream'] ?? '') === 'jobs') {
    nfSSE::stream(fn () => ['queues' => JobDashboard::queues()], ['interval' => 2]);
    exit;
}
echo (new JobDashboard())->render('?stream=jobs');
PHP) ?>

	<h3>Documento en la colección <code>jobs</code></h3>
	<?= docCode(<<<'JS'
{
  queue: "email",
  class: "EnviarCorreoJob",
  payload: { para: "...", asunto: "...", html: "..." },
  attempts: 0,
  max_attempts: 5,
  priority: 0,                   // mayor = se toma antes
  delay_until: ISODate("..."),   // no se ejecuta antes de esta hora
  created_at: ISODate("..."),
  failed_at: null,               // fecha si agotó los intentos
  error: null
}
JS, 'javascript') ?>
</div>
