<?
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

abstract class Job
{
    public $queue = 'default';
    public $maxAttempts = 3;
    public $delay = 0;
    public $attempts = 0;
    public $_id;

    abstract public function handle();

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
            $this->fail();
        }
    }

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
