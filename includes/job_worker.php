#!/usr/bin/env php
<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only script');
}
/*
 * Worker de la cola de trabajos (colección jobs; ver includes/class.Job.php y Admin → Workers).
 *
 *   HTTP_HOST=www.ejemplo.com php job_worker.php [cola] [reserva] [--once]
 *
 * - HTTP_HOST elige el bloque de config.php (la base del sitio); sin él se usa localhost.
 * - cola: la de los trabajos a procesar (default).
 * - reserva: segundos que un trabajo tomado queda apartado; si otro worker lo ve después de ese tiempo lo vuelve
 *   a ejecutar, así que con varios workers por cola use más que lo que tarda el trabajo más largo (5).
 * - --once: procesa lo pendiente y termina (para cron); sin él corre indefinidamente (supervisor/systemd).
 */
require 'include.php';

$nfWorkerArgs = array_values(array_filter(array_slice($argv, 1), fn ($a) => !str_starts_with($a, '--')));
$queue = $nfWorkerArgs[0] ?? 'default';
$timeout = max(1, (int) ($nfWorkerArgs[1] ?? 5));
$once = in_array('--once', $argv, true);

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
                // Último error, para Admin → Workers (retry()/fail() no lo reciben).
                $m->{$config['sitedb']}->jobs->updateOne(['_id' => $job->_id], ['$set' => ['error' => get_class($e) . ': ' . $e->getMessage()]]);
            } else {
                $m->{$config['sitedb']}->jobs->updateOne(['_id' => $job->_id], ['$set' => ['failed_at' => new MongoDB\BSON\UTCDateTime(), 'error' => $e->getMessage()]]);
            }
        }
    } elseif ($once) {
        break;
    } else {
        sleep(1);
    }
}
