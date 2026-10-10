<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Caché local en archivos: nfCacheRemember, nfCacheForget y nfCacheDir.
 */
class CacheTest extends TestCase
{
    private $config;
    private string $dir;

    protected function setUp(): void
    {
        $this->config = $GLOBALS['config'];
        $this->dir = sys_get_temp_dir() . '/nframework_cache_test_' . getmypid() . '_' . uniqid();
        $GLOBALS['config']['cache_dir'] = $this->dir;
        $GLOBALS['config']['sitedb'] = 'pruebas';
    }

    protected function tearDown(): void
    {
        $GLOBALS['config'] = $this->config;
        foreach (glob($this->dir . '/pruebas/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir . '/pruebas');
        @rmdir($this->dir);
    }

    public function testRememberCallsLoaderOnlyOnce(): void
    {
        $calls = 0;
        $loader = function () use (&$calls) {
            $calls++;
            return ['valor' => 42, 'fecha' => new \MongoDB\BSON\UTCDateTime(0)];
        };
        $first = nfCacheRemember('clave', 60, $loader);
        $second = nfCacheRemember('clave', 60, $loader);

        $this->assertSame(1, $calls);
        $this->assertSame(42, $second['valor']);
        $this->assertInstanceOf(\MongoDB\BSON\UTCDateTime::class, $second['fecha']);
        $this->assertEquals($first, $second);
    }

    public function testNullAndFalseAreCached(): void
    {
        $calls = 0;
        foreach ([null, false] as $valor) {
            $loader = function () use (&$calls, $valor) {
                $calls++;
                return $valor;
            };
            nfCacheRemember('vacio' . var_export($valor, true), 60, $loader);
            $this->assertSame($valor, nfCacheRemember('vacio' . var_export($valor, true), 60, $loader));
        }
        $this->assertSame(2, $calls);
    }

    public function testExpiredEntryIsReloaded(): void
    {
        nfCacheRemember('caduca', 60, fn() => 'viejo');
        touch($this->dir . '/pruebas/' . md5('caduca') . '.cache', time() - 120);
        $this->assertSame('nuevo', nfCacheRemember('caduca', 60, fn() => 'nuevo'));
    }

    public function testZeroTtlDisablesCache(): void
    {
        $calls = 0;
        $loader = function () use (&$calls) {
            return ++$calls;
        };
        nfCacheRemember('sincache', 0, $loader);
        $this->assertSame(2, nfCacheRemember('sincache', 0, $loader));
    }

    public function testForgetOneAndAll(): void
    {
        nfCacheRemember('a', 60, fn() => 'a1');
        nfCacheRemember('b', 60, fn() => 'b1');

        nfCacheForget('a');
        $this->assertSame('a2', nfCacheRemember('a', 60, fn() => 'a2'));
        $this->assertSame('b1', nfCacheRemember('b', 60, fn() => 'b2'));

        nfCacheForget();
        $this->assertSame('b3', nfCacheRemember('b', 60, fn() => 'b3'));
    }

    public function testDirectoryIsPrivate(): void
    {
        nfCacheRemember('x', 60, fn() => 1);
        $this->assertSame('0700', substr(sprintf('%o', fileperms($this->dir . '/pruebas')), -4));
    }
}
