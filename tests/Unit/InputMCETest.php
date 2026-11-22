<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * inputMCE: dónde se pueden guardar las imágenes del editor y que no se sobrescriban.
 */
class InputMCETest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir() . '/nf_mce_' . bin2hex(random_bytes(4));
        mkdir($this->tmp . '/fw/nframework', 0777, true);
        mkdir($this->tmp . '/fw/docs/tmp', 0777, true);
        mkdir($this->tmp . '/sitio/media', 0777, true);
    }

    protected function tearDown(): void
    {
        delTree($this->tmp);
    }

    public function testUploadDirRejectsFrameworkAndInvalidFolders(): void
    {
        $fw = $this->tmp . '/fw';
        $real = realpath($this->tmp);
        foreach ([null, '', 'media/', '/', $fw, $fw . '/', $fw . '/nframework', $fw . '/nframework/sub/', $fw . '/includes/x',
            $fw . '/admin', $fw . '/sat/certs', $this->tmp . '/sitio/../fw/nframework', "/tmp/a\0b"] as $dir) {
            $this->assertNull(\inputMCE::uploadDir($dir, $fw), var_export($dir, true));
        }
        $this->assertSame($real . '/sitio/media/', \inputMCE::uploadDir($this->tmp . '/sitio/media', $fw));
        $this->assertSame($real . '/sitio/media/2026/11/', \inputMCE::uploadDir($this->tmp . '/sitio/media/2026/11', $fw), 'Puede no existir aún');
        $this->assertSame($real . '/fw/docs/tmp/', \inputMCE::uploadDir($fw . '/docs/tmp/', $fw));
    }

    public function testUploadDirFollowsSymlinksIntoTheFramework(): void
    {
        $link = $this->tmp . '/sitio/atajo';
        symlink($this->tmp . '/fw/nframework', $link);
        $this->assertNull(\inputMCE::uploadDir($link . '/', $this->tmp . '/fw'));
    }

    public function testUniqueFilenameNeverOverwrites(): void
    {
        $dir = $this->tmp . '/sitio/media/';
        $this->assertSame('mceclip0.jpg', \inputMCE::uniqueFilename($dir, 'mceclip0.jpg'));
        touch($dir . 'mceclip0.jpg');
        touch($dir . 'mceclip0-1.jpg');
        $this->assertSame('mceclip0-2.jpg', \inputMCE::uniqueFilename($dir, 'mceclip0.jpg'));
    }
}
