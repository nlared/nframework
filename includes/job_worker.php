#!/usr/bin/env php
<?php
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
        try {
            $instance = new $job->class($job->payload);
            $instance->_id = $job->_id;
            $instance->attempts = $job->attempts;
            $instance->handle();
            $m->{$config['sitedb']}->jobs->deleteOne(['_id' => $job->_id]);
        } catch (Exception $e) {
            $instance->retry(5);
        }
    } else {
        sleep(1);
    }
}
