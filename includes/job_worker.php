#!/usr/bin/env php
<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only script');
}
require 'include.php';

$queue = $argv[1] ?? 'default';
$timeout = $argv[2] ?? 5;

while (true) {
    $job = $m->{$config['sitedb']}->jobs->findOneAndUpdate(
        [
            'queue' => $queue,
            'delay_until' => ['$lte' => new MongoDB\BSON\UTCDateTime()],
            'failed_at' => null
        ],
        ['$set' => ['delay_until' => new MongoDB\BSON\UTCDateTime((time() + $timeout) * 1000)]],
        ['returnDocument' => MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER]
    );

    if ($job) {
        $instance = null;
        try {
            // Solo se instancian clases Job: el nombre de clase viene de la base de datos.
            if (!is_string($job->class) || !is_subclass_of($job->class, 'Job')) {
                throw new RuntimeException('Clase de job inválida: ' . json_encode($job->class));
            }
            $instance = new $job->class($job->payload);
            $instance->_id = $job->_id;
            $instance->attempts = $job->attempts;
            $instance->handle();
            $m->{$config['sitedb']}->jobs->deleteOne(['_id' => $job->_id]);
        } catch (Throwable $e) {
            if ($instance !== null) {
                $instance->retry(5);
            } else {
                $m->{$config['sitedb']}->jobs->updateOne(['_id' => $job->_id], ['$set' => ['failed_at' => new MongoDB\BSON\UTCDateTime(), 'error' => $e->getMessage()]]);
            }
        }
    } else {
        sleep(1);
    }
}
