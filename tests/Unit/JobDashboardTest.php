<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class JobDashboardTest extends TestCase
{
    private string $proc;

    protected function setUp(): void
    {
        $this->proc = sys_get_temp_dir() . '/nfproc_' . uniqid();
        $procs = [
            // worker con host legible
            101 => ["php8.3\0/var/www/fw/includes/job_worker.php\0email\0" . "300\0", "PATH=/usr/bin\0HTTP_HOST=www.uno.com\0"],
            // worker de otro sitio, opciones de php antes del script y --once
            102 => ["/usr/bin/php\0-d\0memory_limit=1G\0job_worker.php\0default\0--once\0", "HTTP_HOST=www.dos.com\0"],
            // worker de otro usuario: environ no legible
            103 => ["php\0job_worker.php\0", null],
            // no son workers: sudo que lanzó uno, un editor y grep
            104 => ["sudo\0-u\0www-data\0php\0job_worker.php\0", "HTTP_HOST=www.uno.com\0"],
            105 => ["vim\0job_worker.php\0", "HTTP_HOST=www.uno.com\0"],
            106 => ["grep\0job_worker.php\0", null],
            107 => ["php-fpm: pool www\0", null],
        ];
        foreach ($procs as $pid => [$cmdline, $environ]) {
            mkdir("$this->proc/$pid", 0700, true);
            file_put_contents("$this->proc/$pid/cmdline", $cmdline);
            file_put_contents("$this->proc/$pid/status", "Name:\tphp\nVmRSS:\t   2048 kB\n");
            if ($environ !== null) {
                file_put_contents("$this->proc/$pid/environ", $environ);
            }
        }
    }

    protected function tearDown(): void
    {
        delTree($this->proc);
    }

    public function testWorkersOnlyCountsPhpProcessesRunningTheWorker(): void
    {
        $workers = \JobDashboard::workers($this->proc);
        usort($workers, fn ($a, $b) => $a['pid'] <=> $b['pid']);
        $this->assertSame([101, 102, 103], array_column($workers, 'pid'));
        $this->assertSame(['email', 'default', 'default'], array_column($workers, 'queue'));
        $this->assertSame([300, 5, 5], array_column($workers, 'lease'));
        $this->assertSame(['www.uno.com', 'www.dos.com', null], array_column($workers, 'host'));
        $this->assertSame(2048 * 1024, $workers[0]['rss']);
    }

    public function testWorkersForHostIncludesUnknownHosts(): void
    {
        $site = \JobDashboard::workersForHost(\JobDashboard::workers($this->proc), 'WWW.UNO.COM');
        $pids = array_column($site, 'pid');
        sort($pids);
        $this->assertSame([101, 103], $pids);
    }

    public function testParseWorkerRejectsNonPhp(): void
    {
        $this->assertNull(\JobDashboard::parseWorker("bash\0-c\0php job_worker.php\0", null));
        $this->assertSame(['queue' => 'q1', 'lease' => 60, 'host' => null], \JobDashboard::parseWorker("php8.4\0./job_worker.php\0q1\0" . "60\0", ''));
    }
}
