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
 * reintenta en 5 s hasta maxAttempts; después marca failed_at. Ver docs/jobs.php.
 *
 *   class EnviarCorreoJob extends Job {
 *       public $queue = 'email';
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
        $m->{$config['sitedb']}->jobs->insertOne([
            'queue' => $job->queue,
            'class' => static::class,
            'payload' => $job->payload,
            'attempts' => 0,
            'max_attempts' => $job->maxAttempts,
            'delay_until' => new MongoDB\BSON\UTCDateTime((time() + $job->delay) * 1000),
            'created_at' => new MongoDB\BSON\UTCDateTime(),
        ]);
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
