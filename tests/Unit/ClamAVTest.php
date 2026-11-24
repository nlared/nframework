<?php

namespace Tests\Unit;

use ClamAV;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/class.ClamAV.php';

/**
 * Cliente de clamd: opciones, validación del panel y lectura de respuestas (sin servidor real).
 */
class ClamAVTest extends TestCase
{
    public function testNormalizeCompletaValoresPorOmision(): void
    {
        $this->assertSame(
            ['host' => 'av.local', 'port' => 3310, 'timeout' => 10, 'maxsize' => 25],
            ClamAV::normalize(['host' => ' av.local ', 'port' => '', 'timeout' => 'x', 'maxsize' => 0])
        );
        $this->assertSame(['host' => '', 'port' => 3311, 'timeout' => 5, 'maxsize' => 100],
            ClamAV::normalize(['host' => ['$ne' => 1], 'port' => '3311', 'timeout' => 5, 'maxsize' => '100']));
    }

    public function testValidationError(): void
    {
        $this->assertSame('', ClamAV::validationError(['host' => '127.0.0.1', 'port' => '3310', 'timeout' => '10', 'maxsize' => '25']));
        $this->assertSame('', ClamAV::validationError(['host' => 'clamav.interno', 'port' => '']));
        $this->assertSame('', ClamAV::validationError(['host' => '::1']));
        $this->assertSame('', ClamAV::validationError(['host' => ''], false));
        $this->assertStringContainsString('Indique el servidor', ClamAV::validationError(['host' => '']));
        $this->assertStringContainsString('sin tcp://', ClamAV::validationError(['host' => 'tcp://127.0.0.1:3310']));
        $this->assertStringContainsString('puerto', ClamAV::validationError(['host' => 'av', 'port' => '70000']));
        $this->assertStringContainsString('tiempo de espera', ClamAV::validationError(['host' => 'av', 'timeout' => '0']));
        $this->assertStringContainsString('tamaño máximo', ClamAV::validationError(['host' => 'av', 'maxsize' => '1.5']));
    }

    public function testParseResponse(): void
    {
        $this->assertSame(['status' => 'OK', 'virus' => null, 'message' => 'stream: OK'], ClamAV::parseResponse("stream: OK\0"));
        $this->assertSame('Win.Test.EICAR_HDB-1', ClamAV::parseResponse("stream: Win.Test.EICAR_HDB-1 FOUND\0")['virus']);
        $this->assertSame('FOUND', ClamAV::parseResponse("1: stream: Eicar-Signature FOUND\n")['status']);
        $error = ClamAV::parseResponse("INSTREAM size limit exceeded. ERROR\0");
        $this->assertSame('ERROR', $error['status']);
        $this->assertSame('INSTREAM size limit exceeded.', $error['message']);
        $this->assertSame('ERROR', ClamAV::parseResponse('')['status']);
    }

    public function testDecide(): void
    {
        $this->assertNull(ClamAV::decide(['status' => 'OK']));
        $this->assertStringContainsString('Eicar', ClamAV::decide(['status' => 'FOUND', 'virus' => 'Eicar']));
        $this->assertStringContainsString('Eicar', ClamAV::decide(['status' => 'FOUND', 'virus' => 'Eicar'], 'accept'));
        $caido = ['status' => 'ERROR', 'virus' => null, 'message' => 'No se pudo conectar a clamd en 10.0.0.5:3310'];
        $this->assertNull(ClamAV::decide($caido, 'accept'));
        $this->assertStringNotContainsString('10.0.0.5', ClamAV::decide($caido));
        $grande = (new ClamAV(['host' => 'a', 'maxsize' => 1]))->scanString(str_repeat('x', 1048577));
        $this->assertStringContainsString('1 MB', ClamAV::decide($grande));
    }

    public function testUploadErrorSinHabilitarNoAnaliza(): void
    {
        $original = $GLOBALS['config'];
        try {
            $this->uploadErrorCases();
        } finally {
            $GLOBALS['config'] = $original;
        }
    }

    private function uploadErrorCases(): void
    {
        $GLOBALS['config'] = ['clamav' => ['enabled' => false, 'host' => '127.0.0.1', 'port' => 1]];
        $this->assertNull(ClamAV::uploadError(__FILE__));
        $GLOBALS['config'] = ['clamav' => ['enabled' => true, 'host' => '127.0.0.1', 'port' => 1, 'timeout' => 1]];
        $this->assertNotNull(ClamAV::uploadError(__FILE__));
        $GLOBALS['config']['clamav']['onerror'] = 'accept';
        $this->assertNull(ClamAV::uploadError(__FILE__));
        $this->assertFileExists(__FILE__);
    }

    public function testConfigOptionsAceptaObjetos(): void
    {
        $this->assertSame(['host' => 'a'], ClamAV::configOptions((object) ['host' => 'a']));
        $this->assertSame([], ClamAV::configOptions('x'));
    }

    public function testEicarEsLaFirmaEstandar(): void
    {
        $this->assertSame(68, strlen(ClamAV::eicar()));
        $this->assertSame('3395856ce81f2b7382dee72602f798b642f14140', sha1(ClamAV::eicar()));
    }

    public function testSinServidorDevuelveError(): void
    {
        $av = new ClamAV(['host' => '127.0.0.1', 'port' => 1, 'timeout' => 1]);
        $this->assertFalse($av->ping());
        $this->assertSame('ERROR', $av->scanString('hola')['status']);
        $this->assertSame('ERROR', (new ClamAV(['host' => 'a', 'maxsize' => 1]))->scanString(str_repeat('x', 1048577))['status']);
    }

    /**
     * Servidor clamd simulado en un proceso hijo: responde PING y analiza INSTREAM buscando EICAR.
     */
    public function testProtocoloContraServidorSimulado(): void
    {
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('Requiere pcntl para simular clamd.');
        }
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $port = (int) substr(strrchr(stream_socket_get_name($server, false), ':'), 1);
        $pid = pcntl_fork();
        if ($pid === 0) {
            for ($i = 0; $i < 3; $i++) {
                $c = stream_socket_accept($server, 5);
                $cmd = stream_get_line($c, 64, "\0");
                if ($cmd === 'zPING') {
                    fwrite($c, "PONG\0");
                } elseif ($cmd === 'zINSTREAM') {
                    $data = '';
                    while (($len = unpack('N', fread($c, 4))[1]) > 0) {
                        $data .= stream_get_contents($c, $len);
                    }
                    fwrite($c, str_contains($data, 'EICAR-STANDARD') ? "stream: Eicar-Signature FOUND\0" : "stream: OK\0");
                }
                fclose($c);
            }
            exit(0);
        }
        fclose($server);
        $av = new ClamAV(['host' => '127.0.0.1', 'port' => $port, 'timeout' => 5]);
        $this->assertTrue($av->ping());
        $this->assertSame('OK', $av->scanString(str_repeat('limpio ', 5000))['status']);
        $this->assertSame('Eicar-Signature', $av->scanString(ClamAV::eicar())['virus']);
        pcntl_waitpid($pid, $status);
    }
}
