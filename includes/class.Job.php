<?php
// includes/class.Job.php

/* Colección: jobs  
{  
  _id: ObjectId,  
  queue: string,          // nombre de la cola (default, email, reports)  
  class: string,          // clase job a ejecutar (ej: SendEmailJob)  
  payload: object,        // datos serializados del job  
  attempts: int,          // intentos realizados  
  max_attempts: int,      // intentos máximos (default 3)  
  priority: int,          // mayor = se toma antes (default 0)  
  delay_until: UTCDateTime, // ejecución diferida  
  created_at: UTCDateTime,  
  failed_at: UTCDateTime|null,  
  error: string|null  
}


// Despachar un job  
SendEmailJob::dispatch(['to' => 'user@example.com', 'subject' => 'Hola']);  
  
// Iniciar worker (CLI)  
php job_worker.php default 5  

*/

/**
 * Base de los trabajos en cola (colección jobs). La subclase vive en class.<Nombre>.php dentro del
 * include_path, recibe y guarda $payload en su constructor e implementa handle(); job_worker.php la
 * instancia con new Clase($payload), le asigna _id y attempts, y llama handle(). Una excepción en handle()
 * reintenta en 5 s hasta maxAttempts; después marca failed_at. Entre los trabajos ya vencidos de la cola el
 * worker toma primero el de mayor $priority y, a igual prioridad, el que venció antes. Ver docs/jobs.php.
 *
 *   class EnviarCorreoJob extends Job {
 *       public $queue = 'email';
 *       public $priority = 10;          // antes que los de prioridad 0 de la misma cola
 *       public function __construct(public $payload = []) {}
 *       public function handle() { ... }
 *   }
 *   EnviarCorreoJob::dispatch(['para' => $correo]);
 *
 * @property mixed $payload Datos del trabajo; la subclase lo asigna en su constructor.
 */
abstract class Job
{
    /** @var string Cola en la que se encola (php job_worker.php <cola>). */
    public $queue = 'default';
    /** @var int Intentos antes de marcarlo como fallido. */
    public $maxAttempts = 3;
    /** @var int Segundos antes de la primera ejecución. */
    public $delay = 0;
    /**
     * @var int Prioridad dentro de la cola: mayor se toma antes (negativos = después de los normales). Solo
     *      ordena los trabajos ya vencidos; con trabajos de alta prioridad llegando sin pausa, los de baja esperan.
     *      La subclase puede fijarla en su constructor a partir de $payload para variarla por trabajo.
     */
    public $priority = 0;
    /** @var int Intentos realizados (lo asigna el worker). */
    public $attempts = 0;
    /** @var MongoDB\BSON\ObjectId|null _id del documento en jobs (lo asigna el worker). */
    public $_id;

    /**
     * Ejecuta el trabajo. Lance una excepción para que se reintente.
     *
     * @return mixed
     */
    abstract public function handle();

    /**
     * Encola un trabajo de esta clase con $payload; se ejecuta después de $delay segundos.
     *
     * @global MongoDB\Client $m
     * @global class_config $config
     * @param mixed $payload Datos serializables a BSON (sin objetos de PHP).
     * @return void
     */
    public static function dispatch($payload = [])
    {
        global $m, $config;
        $job = new static($payload);
        $m->{$config['sitedb']}->jobs->insertOne($job->toJobDocument());
    }

    /**
     * Documento que dispatch() guarda en jobs para este trabajo.
     *
     * @param int|null $now Marca de tiempo Unix (null = ahora); para pruebas.
     * @return array{queue: string, class: string, payload: mixed, attempts: int, max_attempts: int, priority: int, delay_until: MongoDB\BSON\UTCDateTime, created_at: MongoDB\BSON\UTCDateTime}
     */
    public function toJobDocument($now = null)
    {
        $now ??= time();
        return [
            'queue' => (string) $this->queue,
            'class' => static::class,
            'payload' => $this->payload,
            'attempts' => 0,
            'max_attempts' => (int) $this->maxAttempts,
            'priority' => (int) $this->priority,
            'delay_until' => new MongoDB\BSON\UTCDateTime(($now + (int) $this->delay) * 1000),
            'created_at' => new MongoDB\BSON\UTCDateTime($now * 1000),
        ];
    }

    /**
     * Argumentos de findOneAndUpdate() con que job_worker.php toma el siguiente trabajo de $queue: vencido, no
     * fallido, de mayor prioridad y, a igual prioridad, el que venció antes; queda reservado $lease segundos.
     * Usa el índice {queue, failed_at, priority: -1, delay_until} de nfIndexes.
     *
     * @param string $queue Cola.
     * @param int $lease Segundos que el trabajo queda apartado para este worker.
     * @param int|null $now Marca de tiempo Unix (null = ahora); para pruebas.
     * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: array<string, mixed>} [filtro, actualización, opciones]
     */
    public static function claimQuery($queue, $lease, $now = null)
    {
        $now ??= time();
        return [
            [
                'queue' => (string) $queue,
                'delay_until' => ['$lte' => new MongoDB\BSON\UTCDateTime($now * 1000)],
                'failed_at' => null,
            ],
            ['$set' => ['delay_until' => new MongoDB\BSON\UTCDateTime(($now + (int) $lease) * 1000)]],
            [
                'sort' => ['priority' => -1, 'delay_until' => 1],
                'returnDocument' => MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER,
            ],
        ];
    }

    /**
     * Cuenta un intento y lo reprograma en $delay segundos, o lo marca como fallido si ya agotó maxAttempts.
     *
     * @global MongoDB\Client $m
     * @global class_config $config
     * @param int $delay Segundos.
     * @return void
     */
    public function retry($delay = 5)
    {
        global $m, $config;
        $this->attempts++;
        if ($this->attempts < $this->maxAttempts) {
            $m->{$config['sitedb']}->jobs->updateOne(
                ['_id' => $this->_id],
                ['$set' => [
                    'attempts' => $this->attempts,
                    'delay_until' => new MongoDB\BSON\UTCDateTime((time() + $delay) * 1000),
                ]]
            );
        } else {
            $m->{$config['sitedb']}->jobs->updateOne(['_id' => $this->_id], ['$set' => ['attempts' => $this->attempts]]);
            $this->fail();
        }
    }

    /**
     * Marca el trabajo como fallido (failed_at, error); el worker ya no lo toma.
     *
     * @global MongoDB\Client $m
     * @global class_config $config
     * @param string|null $error
     * @return void
     */
    public function fail($error = null)
    {
        global $m, $config;
        $m->{$config['sitedb']}->jobs->updateOne(
            ['_id' => $this->_id],
            ['$set' => [
                'failed_at' => new MongoDB\BSON\UTCDateTime(),
                'error' => $error,
            ]]
        );
    }
}
