<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/class.Job.php';

/** Trabajo de prueba: la prioridad sale del payload, como en docs/jobs.php. */
class PrioridadPruebaJob extends \Job
{
    public $queue = 'reports';
    public $maxAttempts = 4;
    public $delay = 30;

    public function __construct(public $payload = [])
    {
        $this->priority = !empty($payload['urgente']) ? 10 : -5;
    }

    public function handle()
    {
    }
}

/**
 * Job: documento que guarda dispatch() y consulta con que job_worker.php toma el siguiente trabajo.
 */
class JobTest extends TestCase
{
    public function testDocumentoIncluyePrioridadYTiempos(): void
    {
        $doc = (new PrioridadPruebaJob(['urgente' => true]))->toJobDocument(1_000_000);

        $this->assertSame('reports', $doc['queue']);
        $this->assertSame(PrioridadPruebaJob::class, $doc['class']);
        $this->assertSame(['urgente' => true], $doc['payload']);
        $this->assertSame(0, $doc['attempts']);
        $this->assertSame(4, $doc['max_attempts']);
        $this->assertSame(10, $doc['priority']);
        $this->assertSame('1000030000', (string) $doc['delay_until']);
        $this->assertSame('1000000000', (string) $doc['created_at']);
        $this->assertSame(-5, (new PrioridadPruebaJob([]))->toJobDocument(1)['priority']);
    }

    public function testPrioridadSeGuardaComoEntero(): void
    {
        $job = new PrioridadPruebaJob([]);
        $job->priority = '7';
        $this->assertSame(7, $job->toJobDocument(1)['priority']);
    }

    public function testClaimQueryOrdenaPorPrioridadYVencimiento(): void
    {
        [$filter, $update, $options] = \Job::claimQuery('email', 300, 2_000_000);

        $this->assertSame('email', $filter['queue']);
        $this->assertNull($filter['failed_at']);
        $this->assertSame('2000000000', (string) $filter['delay_until']['$lte']);
        $this->assertSame('2000300000', (string) $update['$set']['delay_until']);
        $this->assertSame(['priority' => -1, 'delay_until' => 1], $options['sort']);
        $this->assertSame(\MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER, $options['returnDocument']);
    }

    public function testExisteIndiceParaLaConsultaDelWorker(): void
    {
        $keys = array_map(fn ($i) => $i['key'], array_filter(\nfIndexes::required([]), fn ($i) => $i['collection'] === 'jobs'));
        $this->assertContains(['queue' => 1, 'failed_at' => 1, 'priority' => -1, 'delay_until' => 1], $keys);
    }
}
