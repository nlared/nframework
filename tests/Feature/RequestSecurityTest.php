<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * Protección CSRF por Origin/Referer (nfIsTrustedRequestOrigin) y redirecciones seguras (nfSafeRedirect).
 */
class RequestSecurityTest extends TestCase
{
    private array $server;
    private $config;

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        $this->config = $GLOBALS['config'];
        $GLOBALS['config']['url'] = 'https://www.ejemplo.com';
        $GLOBALS['config']['allowed_redirect_hosts'] = ['sso.ejemplo.com'];
        $_SERVER['HTTP_HOST'] = 'www.ejemplo.com';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/admin/users/';
        unset($_SERVER['HTTP_ORIGIN'], $_SERVER['HTTP_REFERER']);
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        $GLOBALS['config'] = $this->config;
    }

    public function testGetIsAlwaysAllowed(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_ORIGIN'] = 'https://atacante.com';
        $this->assertTrue(nfIsTrustedRequestOrigin());
    }

    public function testPostWithoutOriginOrRefererIsAllowed(): void
    {
        // Webhooks y llamadas servidor a servidor.
        $this->assertTrue(nfIsTrustedRequestOrigin());
    }

    public function testPostFromSameHostIsAllowed(): void
    {
        $_SERVER['HTTP_ORIGIN'] = 'https://www.ejemplo.com';
        $this->assertTrue(nfIsTrustedRequestOrigin());
    }

    public function testPostFromForeignOriginIsRejected(): void
    {
        $_SERVER['HTTP_ORIGIN'] = 'https://atacante.com';
        $this->assertFalse(nfIsTrustedRequestOrigin());
    }

    public function testNullOriginIsRejected(): void
    {
        $_SERVER['HTTP_ORIGIN'] = 'null';
        $this->assertFalse(nfIsTrustedRequestOrigin());
    }

    public function testForeignRefererIsRejectedWhenNoOrigin(): void
    {
        $_SERVER['HTTP_REFERER'] = 'https://atacante.com/form.html';
        $this->assertFalse(nfIsTrustedRequestOrigin());
    }

    public function testLookalikeHostIsRejected(): void
    {
        $_SERVER['HTTP_ORIGIN'] = 'https://www.ejemplo.com.atacante.com';
        $this->assertFalse(nfIsTrustedRequestOrigin());
    }

    public function testAllowedAndTrustedHostsAreAccepted(): void
    {
        $_SERVER['HTTP_ORIGIN'] = 'https://sso.ejemplo.com';
        $this->assertTrue(nfIsTrustedRequestOrigin());

        $GLOBALS['config']['csrf_trusted_origins'] = "pagos.ejemplo.com\n";
        $_SERVER['HTTP_ORIGIN'] = 'https://pagos.ejemplo.com';
        $this->assertTrue(nfIsTrustedRequestOrigin());
    }

    public function testExemptPathSkipsCheck(): void
    {
        $GLOBALS['config']['csrf_exempt_paths'] = ['/webhooks/'];
        $_SERVER['REQUEST_URI'] = '/webhooks/pago?x=1';
        $_SERVER['HTTP_ORIGIN'] = 'https://atacante.com';
        $this->assertTrue(nfIsTrustedRequestOrigin());
    }

    public function testSafeRedirect(): void
    {
        $this->assertSame('/admin/', nfSafeRedirect('/admin/'));
        $this->assertSame('/', nfSafeRedirect('//atacante.com/'));
        $this->assertSame('/', nfSafeRedirect('https://atacante.com/'));
        $this->assertSame('/', nfSafeRedirect('javascript:alert(1)'));
        $this->assertSame('https://sso.ejemplo.com/x', nfSafeRedirect('https://sso.ejemplo.com/x'));
    }
}
