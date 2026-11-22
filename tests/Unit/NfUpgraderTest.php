<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Colección en memoria: lo que nfUpgrader usa de MongoDB\Collection. */
class MemoryCollection
{
    /** @var array<string, array<string, mixed>> */
    public array $docs = [];

    public function find($filter = [])
    {
        $skip = $filter['_id']['$nin'] ?? [];
        return array_values(array_filter($this->docs, fn($d) => !in_array($d['_id'], $skip, true)));
    }

    public function findOne($filter)
    {
        return $this->docs[$filter['_id']] ?? null;
    }

    public function replaceOne($filter, $doc, $options = [])
    {
        $this->docs[$filter['_id']] = $doc;
    }

    public function updateOne($filter, $update, $options = [])
    {
        $this->docs[$filter['_id']] = ($update['$set'] ?? []) + ($this->docs[$filter['_id']] ?? ['_id' => $filter['_id']]);
    }

    public function deleteOne($filter)
    {
        unset($this->docs[$filter['_id']]);
    }
}

/** Base en memoria: $db->coleccion. */
class MemoryDatabase
{
    /** @var array<string, MemoryCollection> */
    public array $collections = [];

    public function __get($name)
    {
        return $this->collections[$name] ??= new MemoryCollection();
    }
}

/**
 * nfUpgrader: versión, descubrimiento y orden de migraciones, registro por sitio y revisión del código.
 */
class NfUpgraderTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir() . '/nf_up_' . bin2hex(random_bytes(4));
        mkdir($this->tmp . '/fw', 0777, true);
        mkdir($this->tmp . '/sitio/upgrades', 0777, true);
        mkdir($this->tmp . '/sitio/vendor/x', 0777, true);
        $GLOBALS['nfUpgraderCalls'] = [];
    }

    protected function tearDown(): void
    {
        delTree($this->tmp);
        unset($GLOBALS['nfUpgraderCalls']);
    }

    private function migration(string $dir, string $name, string $body): void
    {
        file_put_contents("$dir/$name", "<?php\nreturn $body;\n");
    }

    public function testFrameworkVersionFile(): void
    {
        $this->assertMatchesRegularExpression('/^\d{4}\.\d+$/', \nfUpgrader::frameworkVersion(), 'VERSION del framework');
        $this->assertSame('0', \nfUpgrader::frameworkVersion($this->tmp));
        file_put_contents($this->tmp . '/VERSION', "2026.3\n");
        $this->assertSame('2026.3', \nfUpgrader::frameworkVersion($this->tmp));
    }

    public function testFrameworkMigrationsAreValid(): void
    {
        $errors = [];
        $all = \nfUpgrader::discover(['framework' => dirname(__DIR__, 2) . '/upgrades'], $errors);
        $this->assertSame([], $errors);
        $this->assertNotEmpty($all);
        foreach ($all as $mg) {
            $this->assertNotSame('', $mg['description'], $mg['id']);
            $this->assertTrue(version_compare($mg['version'], \nfUpgrader::frameworkVersion(), '<='), $mg['id'] . ': versión posterior a VERSION');
        }
    }

    public function testDiscoverOrdersAndValidates(): void
    {
        $fw = $this->tmp . '/fw';
        $site = $this->tmp . '/sitio/upgrades';
        $this->migration($fw, '20260102_b.php', "['version' => '2026.1', 'description' => 'B', 'up' => fn() => null]");
        $this->migration($fw, '20260101_a.php', "['version' => '2026.1', 'description' => 'A', 'up' => fn() => null]");
        $this->migration($site, '20250101_propia.php', "['description' => 'Sitio', 'up' => fn() => null]");
        $this->migration($fw, '20260103_mala.php', "['description' => 'Sin up ni code']");
        $this->migration($fw, '20260104_regex.php', "['code' => [['pattern' => '/(/']]]");
        file_put_contents("$fw/notas.php", '<?php return 1;');   // otro nombre: se ignora

        $errors = [];
        $all = \nfUpgrader::discover(['framework' => $fw, 'sitio' => $site], $errors);
        $this->assertSame(['framework:20260101_a', 'framework:20260102_b', 'sitio:20250101_propia'], array_column($all, 'id'),
            'Primero el framework, luego el sitio; cada uno por nombre');
        $this->assertCount(2, $errors);
    }

    public function testRunAppliesOnceInOrderAndStopsOnFailure(): void
    {
        $fw = $this->tmp . '/fw';
        $this->migration($fw, '20260101_uno.php', "['description' => 'uno', 'up' => function (\$db, \$log) { \$GLOBALS['nfUpgraderCalls'][] = 'uno'; \$log('hecho'); }]");
        $this->migration($fw, '20260102_falla.php', "['description' => 'falla', 'up' => function () { \$GLOBALS['nfUpgraderCalls'][] = 'falla'; throw new RuntimeException('sin conexión'); }]");
        $this->migration($fw, '20260103_tres.php', "['description' => 'tres', 'up' => function () { \$GLOBALS['nfUpgraderCalls'][] = 'tres'; }]");
        $db = new MemoryDatabase();
        $up = new \nfUpgrader($db, ['framework' => $fw]);

        $dry = $up->run(true);
        $this->assertSame([], $GLOBALS['nfUpgraderCalls'], 'Simular no ejecuta nada');
        $this->assertStringContainsString('Pendiente: framework:20260101_uno', implode("\n", $dry['log']));

        $r = $up->run();
        $this->assertFalse($r['ok']);
        $this->assertSame('framework:20260102_falla', $r['failed']);
        $this->assertSame(['uno', 'falla'], $GLOBALS['nfUpgraderCalls'], 'La que sigue a una fallida no corre');
        $saved = $db->nfmigrations->docs;
        $this->assertTrue($saved['framework:20260101_uno']['ok']);
        $this->assertSame('hecho', $saved['framework:20260101_uno']['salida']);
        $this->assertFalse($saved['framework:20260102_falla']['ok']);
        $this->assertStringContainsString('sin conexión', $saved['framework:20260102_falla']['salida']);
        $this->assertArrayNotHasKey(\nfUpgrader::STATE_ID, $saved, 'Con una falla no se registra la versión');
        $this->assertArrayNotHasKey(\nfUpgrader::LOCK_ID, $saved, 'El candado se libera');

        // Se corrige la migración: la siguiente ejecución retoma desde la fallida, sin repetir la primera.
        $this->migration($fw, '20260102_falla.php', "['description' => 'falla', 'up' => function () { \$GLOBALS['nfUpgraderCalls'][] = 'arreglada'; }]");
        $GLOBALS['nfUpgraderCalls'] = [];
        $r = $up->run();
        $this->assertTrue($r['ok']);
        $this->assertSame(['arreglada', 'tres'], $GLOBALS['nfUpgraderCalls']);
        $this->assertSame(\nfUpgrader::frameworkVersion(), $db->nfmigrations->docs[\nfUpgrader::STATE_ID]['version']);

        $status = $up->status();
        $this->assertSame([], $status['pending']);
        $this->assertFalse($status['outdated']);
    }

    public function testLockBlocksAConcurrentRun(): void
    {
        $fw = $this->tmp . '/fw';
        $this->migration($fw, '20260101_uno.php', "['description' => 'uno', 'up' => function () { \$GLOBALS['nfUpgraderCalls'][] = 'uno'; }]");
        $db = new MemoryDatabase();
        $db->nfmigrations->docs[\nfUpgrader::LOCK_ID] = ['_id' => \nfUpgrader::LOCK_ID, 'hasta' => time() + 60];
        $r = (new \nfUpgrader($db, ['framework' => $fw]))->run();
        $this->assertFalse($r['ok']);
        $this->assertSame([], $GLOBALS['nfUpgraderCalls']);

        $db->nfmigrations->docs[\nfUpgrader::LOCK_ID]['hasta'] = time() - 1;   // proceso que murió
        $this->assertTrue((new \nfUpgrader($db, ['framework' => $fw]))->run()['ok']);
    }

    public function testCodeScanAndAutomaticFixWithBackup(): void
    {
        $site = $this->tmp . '/sitio';
        file_put_contents("$site/index.php", "<?php\n\$a = new inputmce([]);\nnotify('Hola', 'texto');\n");
        file_put_contents("$site/vendor/x/lib.php", "<?php new inputmce([]);");   // vendor no se revisa
        symlink($this->tmp . '/fw', "$site/nframework");                          // ligas del multisitio tampoco
        file_put_contents($this->tmp . '/fw/x.php', "<?php new inputmce([]);");
        $migrations = \nfUpgrader::discover(['framework' => dirname(__DIR__, 2) . '/upgrades']);

        $hits = \nfUpgrader::scanCode($migrations, $site);
        $this->assertSame(['index.php'], array_values(array_unique(array_column($hits, 'file'))));
        $byLine = array_column($hits, null, 'line');
        $this->assertTrue($byLine[2]['fixable']);
        $this->assertFalse($byLine[3]['fixable']);

        $applied = \nfUpgrader::applyCode($migrations, $site);
        $this->assertSame(['index.php'], $applied['changed']);
        $this->assertStringContainsString('new inputMCE([])', file_get_contents("$site/index.php"));
        $this->assertStringContainsString("notify('Hola'", file_get_contents("$site/index.php"), 'Las reglas sin replace no cambian nada');
        $this->assertStringContainsString('new inputmce([])', file_get_contents($applied['backup'] . '/index.php'), 'Respaldo del original');
        $this->assertStringContainsString('new inputmce', file_get_contents("$site/vendor/x/lib.php"));
        $this->assertSame([], \nfUpgrader::applyCode($migrations, $site)['changed'], 'Una segunda vez no cambia nada');
    }

    public function testSiteMigrationsFolder(): void
    {
        $dirs = \nfUpgrader::defaultDirs($this->tmp . '/sitio');
        $this->assertSame(realpath($this->tmp . '/sitio/upgrades'), $dirs['sitio']);
        $this->assertArrayNotHasKey('sitio', \nfUpgrader::defaultDirs($this->tmp . '/fw'));
        $this->assertArrayNotHasKey('sitio', \nfUpgrader::defaultDirs(dirname(__DIR__, 2)), 'Instalación simple: no se duplican las del framework');
    }
}
