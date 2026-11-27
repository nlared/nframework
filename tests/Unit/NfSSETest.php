<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/class.nfSSE.php';
require_once __DIR__ . '/../../includes/class.bgprocess.php';
require_once __DIR__ . '/../../includes/class.JobDashboard.php';

/**
 * Server-Sent Events: formato de los eventos y opciones; estado de bgprocess y filas de JobDashboard que
 * alimentan los tableros en vivo.
 */
class NfSSETest extends TestCase
{
    public function testFormatEvent(): void
    {
        $this->assertSame("data: {\"a\":1}\n\n", \nfSSE::format('{"a":1}'));
        $this->assertSame("id: 7\nevent: update\ndata: x\n\n", \nfSSE::format('x', 'update', '7'));
        // Cada línea del dato va en su propia línea data:; event e id no pueden partir el evento.
        $this->assertSame("id: 12\nevent: upd\ndata: uno\ndata: dos\ndata: tres\n\n", \nfSSE::format("uno\ndos\r\ntres", "up\nd", "1\n2"));
    }

    public function testOptionsAreClamped(): void
    {
        $_SERVER['HTTP_LAST_EVENT_ID'] = '42';
        $sse = new \nfSSE(['interval' => 0, 'maxtime' => 5000, 'retry' => 1, 'ticks' => 99, 'nope' => 1]);
        unset($_SERVER['HTTP_LAST_EVENT_ID']);
        $this->assertSame(0.2, $sse->interval);
        $this->assertSame(600, $sse->maxtime);
        $this->assertSame(100, $sse->retry);
        $this->assertSame(0, $sse->ticks, 'ticks no se puede fijar');
        $this->assertSame('42', $sse->lastEventId);
    }

    public function testBgprocessSnapshot(): void
    {
        $log = tempnam(sys_get_temp_dir(), 'nfbg');
        file_put_contents($log, "linea cortada\nfinal 1\nfinal 2\n");
        $snap = \bgprocess::snapshot([
            'respaldo' => ['pid' => 123, 'logfile' => $log, 'cmd' => 'x'],
            'parado' => ['pid' => null, 'logfile' => '/no/existe.log'],
        ], 18, fn (int $pid) => $pid === 123);
        unlink($log);
        $this->assertSame(['running' => true, 'pid' => 123, 'log' => "final 1\nfinal 2\n"], $snap['respaldo'], 'sin la primera línea cortada');
        $this->assertSame(['running' => false, 'pid' => null, 'log' => ''], $snap['parado']);
    }

    public function testJobDashboardRowEscapes(): void
    {
        $row = \JobDashboard::row('<b>q</b>', ['pending' => 3, 'overdue' => 0, 'failed' => 2]);
        $this->assertStringContainsString('data-queue="&lt;b&gt;q&lt;/b&gt;"', $row);
        $this->assertStringContainsString('<td data-k="pending">3</td>', $row);
        $this->assertStringContainsString('<td data-k="overdue">0</td>', $row);
        $this->assertStringContainsString('<span class="fg-orange">2</span>', $row);
    }
}
