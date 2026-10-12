<?php

namespace Tests\Unit;

use Efirma;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/class.Efirma.php';

class EfirmaTest extends TestCase
{
    public function testTipoAllowed(): void
    {
        $this->assertTrue(Efirma::tipoAllowed('any', 'fiel'));
        $this->assertTrue(Efirma::tipoAllowed('any', 'csd'));
        $this->assertTrue(Efirma::tipoAllowed('csd', 'csd'));
        $this->assertFalse(Efirma::tipoAllowed('fiel', 'csd'));
        $this->assertFalse(Efirma::tipoAllowed('csd', 'fiel'));
    }

    public function testEncryptDecryptRoundTrip(): void
    {
        $key = random_bytes(32);
        $blob = Efirma::encrypt('{"pass":"secreta"}', $key);
        $this->assertStringNotContainsString('secreta', base64_decode($blob));
        $this->assertSame('{"pass":"secreta"}', Efirma::decrypt($blob, $key));
    }

    public function testDecryptWithOtherKeyFails(): void
    {
        $blob = Efirma::encrypt('dato', random_bytes(32));
        $this->expectException(\RuntimeException::class);
        Efirma::decrypt($blob, random_bytes(32));
    }

    public function testParseValue(): void
    {
        $this->assertSame(['client', '00001000000500000001'], Efirma::parseValue('client:00001000000500000001'));
        $this->assertSame(['server', '6a049d9a304d4d97f60d3945'], Efirma::parseValue('server:6a049d9a304d4d97f60d3945'));
        $this->assertNull(Efirma::parseValue('otro:123'));
        $this->assertNull(Efirma::parseValue(['$ne' => 1]));
    }

    public function testSecretKeyRequires32Bytes(): void
    {
        $previous = $GLOBALS['config'];
        try {
            $key = random_bytes(32);
            $GLOBALS['config']['efirma_key'] = base64_encode($key);
            $this->assertSame($key, Efirma::secretKey());

            $GLOBALS['config']['efirma_key'] = base64_encode(random_bytes(16));
            $this->expectException(\RuntimeException::class);
            Efirma::secretKey();
        } finally {
            $GLOBALS['config'] = $previous;
        }
    }

    public function testInvalidOptionsThrow(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Efirma(['name' => 'x', 'tipo' => 'pfx']);
    }
}
